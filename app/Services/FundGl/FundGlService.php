<?php

declare(strict_types=1);

namespace App\Services\FundGl;

use App\Enums\FundGl\FundGlStatus;
use App\Enums\Transaction\FinancialTransactionStatus;
use App\Mail\CoreBankMail;
use App\Models\FinancialTransaction;
use App\Models\FundGl;
use App\Models\GeneralLedger;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\GeneralLedger\GeneralLedgerPostingService;
use App\Services\Notification\NotificationService;
use App\Support\ReferenceGenerator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// A separate, real 3-stage maker→reviewer→approver workflow for funding one GL from
// another (source GL → destination GL only — no account/customer party involved).
// Distinct from the 2-stage Posting workflow: a fund_gl needs three DISTINCT users
// (creator, reviewer, approver) before money actually moves. GeneralLedgerPostingService
// remains the only place allowed to touch a GL balance — this class just resolves and
// sequences the calls to it, same division of responsibility as PostingService.
class FundGlService
{
    public function __construct(
        private GeneralLedgerPostingService $glLedger,
        private AuditService $audit,
        private NotificationService $notifications,
    ) {}

    public function list(int $perPage = 15, ?string $from = null, ?string $to = null, ?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        return $this->filtered($from, $to, $search, $status)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    private function filtered(?string $from, ?string $to, ?string $search = null, ?string $status = null): Builder
    {
        return FundGl::query()
            ->when($from, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('reference', 'like', "%{$search}%")
                        ->orWhere('transaction_reference', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%");
                });
            });
    }

    public function find(int $id): FundGl
    {
        return FundGl::with([
            'currency', 'source', 'destination', 'financialTransaction.journalEntries.generalLedger', 'financialTransaction.transactions',
            'createdBy', 'reviewedBy', 'approvedBy', 'rejectedBy',
        ])->findOrFail($id);
    }

    // Get the details, validate them, save the fund_gl as pending review. idempotency_key
    // is always generated here — the client never sends it. Currency is never accepted
    // from the caller — it's always the source GL's own currency.
    public function create(array $data): FundGl
    {
        [$source] = $this->verify((int) $data['source_id'], (int) $data['destination_id'], (string) $data['amount']);

        $actor = Auth::guard('user')->user();

        $fundGl = DB::transaction(fn () => FundGl::create([
            'reference'             => ReferenceGenerator::generate('FGL'),
            'transaction_reference' => ReferenceGenerator::generate('TXN'),
            'idempotency_key'       => (string) Str::uuid(),
            'source_id'             => $data['source_id'],
            'destination_id'        => $data['destination_id'],
            'amount'                => $data['amount'],
            'currency_id'           => $source->currency_id,
            'remarks'               => $data['remarks'] ?? null,
            'status'                => FundGlStatus::PENDING_REVIEW->value,
            'created_by'            => $actor?->id,
        ])->refresh());

        $this->audit->log(
            action: 'created',
            module: 'fund_gls',
            auditable: $fundGl,
            after: $fundGl->toArray(),
            description: "Fund GL request '{$fundGl->reference}' was created by '{$actor?->username}'.",
        );

        $this->notifications->notifyPermission(
            'review_fund_gls',
            'fund_gl',
            $fundGl->id,
            'New GL funding request',
            "Fund GL request '{$fundGl->reference}' for {$fundGl->amount} is awaiting your review.",
        );

        return $fundGl;
    }

    // First stage of the maker→reviewer→approver chain: a second, distinct user reviews
    // a pending fund_gl before it can be approved. Cashiers can never review a fund_gl —
    // they're a teller-facing role, not part of the GL-funding maker-checker chain.
    public function review(FundGl $fundGl): FundGl
    {
        $actor = Auth::guard('user')->user();

        if ($actor?->hasRole('cashier')) {
            throw ValidationException::withMessages([
                'status' => ['Cashiers cannot review fund GL requests.'],
            ]);
        }

        if ($actor && (int) $fundGl->created_by === (int) $actor->id) {
            throw ValidationException::withMessages([
                'status' => ['You cannot review a fund GL request you created yourself.'],
            ]);
        }

        DB::transaction(function () use ($fundGl, $actor) {
            $locked = FundGl::whereKey($fundGl->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== FundGlStatus::PENDING_REVIEW->value) {
                throw ValidationException::withMessages([
                    'status' => ['Only fund GL requests pending review can be reviewed.'],
                ]);
            }

            $fundGl->update([
                'status'      => FundGlStatus::PENDING_APPROVAL->value,
                'reviewed_by' => $actor?->id,
                'reviewed_at' => now(),
            ]);
        });

        $fundGl->refresh();

        $this->audit->log(
            action: 'reviewed',
            module: 'fund_gls',
            auditable: $fundGl,
            after: ['status' => FundGlStatus::PENDING_APPROVAL->value],
            description: "Fund GL request '{$fundGl->reference}' was reviewed by '{$actor?->username}'.",
        );

        $this->notifications->notifyPermission(
            'approve_fund_gls',
            'fund_gl',
            $fundGl->id,
            'Fund GL request awaiting approval',
            "Fund GL request '{$fundGl->reference}' for {$fundGl->amount} is awaiting your approval.",
        );

        return $fundGl;
    }

    // The atomic core — verify, debit source, credit destination, record, all in one
    // DB::transaction. Requires three DISTINCT users across create/review/approve — the
    // approver must differ from both the maker and the reviewer, not just the maker.
    public function approve(FundGl $fundGl): FinancialTransaction
    {
        if ($fundGl->status !== FundGlStatus::PENDING_APPROVAL->value) {
            throw ValidationException::withMessages([
                'status' => ['Only fund GL requests pending approval can be approved.'],
            ]);
        }

        $actor = Auth::guard('user')->user();

        if ($actor?->hasRole('cashier')) {
            throw ValidationException::withMessages([
                'status' => ['Cashiers cannot approve fund GL requests.'],
            ]);
        }

        if ($actor && (int) $fundGl->created_by === (int) $actor->id) {
            throw ValidationException::withMessages([
                'status' => ['You cannot approve a fund GL request you created yourself.'],
            ]);
        }

        if ($actor && (int) $fundGl->reviewed_by === (int) $actor->id) {
            throw ValidationException::withMessages([
                'status' => ['You cannot approve a fund GL request you reviewed yourself — the maker, reviewer, and approver must be three different users.'],
            ]);
        }

        $financialTransaction = DB::transaction(function () use ($fundGl, $actor) {
            // Re-check under a row lock: the unlocked check above is only a fast-path.
            // Without this, two concurrent approve() calls for the same fund_gl could
            // both pass the earlier check and both post.
            $lockedFundGl = FundGl::whereKey($fundGl->id)->lockForUpdate()->firstOrFail();

            if ($lockedFundGl->status !== FundGlStatus::PENDING_APPROVAL->value) {
                throw ValidationException::withMessages([
                    'status' => ['Only fund GL requests pending approval can be approved.'],
                ]);
            }

            return $this->execute($fundGl, $actor);
        });

        $this->audit->log(
            action: 'approved',
            module: 'fund_gls',
            auditable: $fundGl,
            after: ['status' => FundGlStatus::APPROVED->value, 'financial_transaction_id' => $financialTransaction->id],
            description: "Fund GL request '{$fundGl->reference}' was approved by '{$actor?->username}'.",
        );

        $this->sendApprovalEmail($fundGl, $financialTransaction, $actor);

        return $financialTransaction;
    }

    private function execute(FundGl $fundGl, ?User $actor): FinancialTransaction
    {
        [$source, $destination] = $this->verify(
            (int) $fundGl->source_id,
            (int) $fundGl->destination_id,
            (string) $fundGl->amount,
        );

        $financialTransaction = $this->recordFinancialTransaction($fundGl, $actor);

        $baseLeg = [
            'financial_transaction_id' => $financialTransaction->id,
            'entry_group_id'           => (string) Str::uuid(),
            'transaction_reference'    => $fundGl->transaction_reference,
            'amount'                   => $fundGl->amount,
            'currency_id'              => $fundGl->currency_id,
            'narration'                => $fundGl->remarks,
            'created_by'               => $actor?->id,
            'module'                   => 'fund_gls',
            'transaction_type'         => 'fund_gl',
        ];

        $debitLeg = [
            ...$baseLeg,
            'counterparty_type'           => 'general_ledger',
            'counterparty_id'             => $destination->id,
            'counterparty_name'           => $destination->name,
            'counterparty_account_number' => $destination->gl_code,
        ];

        $creditLeg = [
            ...$baseLeg,
            'counterparty_type'           => 'general_ledger',
            'counterparty_id'             => $source->id,
            'counterparty_name'           => $source->name,
            'counterparty_account_number' => $source->gl_code,
        ];

        $this->glLedger->debit($source, $debitLeg);
        $this->glLedger->credit($destination, $creditLeg);

        $fundGl->update([
            'status'                    => FundGlStatus::APPROVED->value,
            'approved_by'               => $actor?->id,
            'approved_at'               => now(),
            'financial_transaction_id'  => $financialTransaction->id,
        ]);

        return $financialTransaction->load(['journalEntries', 'transactions']);
    }

    // Allowed from either pending stage (a reviewer can reject before it even reaches an
    // approver, or an approver can reject at the final stage) — locks + re-checks status
    // so this can't race a concurrent approve() of the same fund_gl.
    public function reject(FundGl $fundGl, string $reason): FundGl
    {
        $actor = Auth::guard('user')->user();

        DB::transaction(function () use ($fundGl, $reason, $actor) {
            $locked = FundGl::whereKey($fundGl->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [FundGlStatus::PENDING_REVIEW->value, FundGlStatus::PENDING_APPROVAL->value], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only fund GL requests pending review or approval can be rejected.'],
                ]);
            }

            $fundGl->update([
                'status'           => FundGlStatus::REJECTED->value,
                'rejected_by'      => $actor?->id,
                'rejected_at'      => now(),
                'rejection_reason' => $reason,
            ]);
        });

        $fundGl->refresh();

        $this->audit->log(
            action: 'rejected',
            module: 'fund_gls',
            auditable: $fundGl,
            after: ['status' => FundGlStatus::REJECTED->value, 'rejection_reason' => $reason],
            description: "Fund GL request '{$fundGl->reference}' was rejected by '{$actor?->username}': {$reason}",
        );

        $this->notifications->notifyUser(
            $fundGl->createdBy,
            'fund_gl',
            $fundGl->id,
            'Fund GL request rejected',
            "Your fund GL request '{$fundGl->reference}' was rejected: {$reason}",
        );

        return $fundGl;
    }

    // Resolves source/destination GLs, locks both rows (fixed id order to avoid
    // deadlocks), checks currency match and that the source can cover the debit.
    private function verify(int $sourceId, int $destinationId, string $amount): array
    {
        if ($sourceId === $destinationId) {
            throw ValidationException::withMessages([
                'destination_id' => ['A fund GL request cannot fund a GL from itself.'],
            ]);
        }

        $ids = [$sourceId, $destinationId];
        sort($ids);

        $locked = [];

        foreach ($ids as $id) {
            $locked[$id] = GeneralLedger::whereKey($id)->lockForUpdate()->firstOrFail();
        }

        $source      = $locked[$sourceId];
        $destination = $locked[$destinationId];

        if ((int) $source->currency_id !== (int) $destination->currency_id) {
            throw ValidationException::withMessages([
                'destination_id' => ['Cannot fund between GLs with different currencies — source and destination must share the same currency.'],
            ]);
        }

        if (! $this->glLedger->hasSufficientBalance($source, $amount)) {
            throw ValidationException::withMessages([
                'amount' => ['Amount exceeds the source GL\'s available balance.'],
            ]);
        }

        return [$source, $destination];
    }

    private function recordFinancialTransaction(FundGl $fundGl, ?User $actor): FinancialTransaction
    {
        return FinancialTransaction::create([
            'transaction_reference' => $fundGl->transaction_reference,
            'idempotency_key'       => $fundGl->idempotency_key,
            'module'                => 'fund_gls',
            'transaction_type'      => 'fund_gl',
            'amount'                => $fundGl->amount,
            'fee'                   => 0,
            'commission'            => 0,
            'vat'                   => 0,
            'discount'              => 0,
            'currency_id'           => $fundGl->currency_id,
            'exchange_rate'         => 1,
            'status'                => FinancialTransactionStatus::COMPLETED->value,
            'narration'             => $fundGl->remarks,
            'created_by'            => $actor?->id,
            'processed_at'          => now(),
            'completed_at'          => now(),
        ]);
    }

    private function sendApprovalEmail(FundGl $fundGl, FinancialTransaction $financialTransaction, ?User $actor): void
    {
        $fundGl->loadMissing(['destination', 'createdBy', 'reviewedBy']);
        $destination = $fundGl->destination;

        Mail::to('admin@bankqbro.com')->send(new CoreBankMail(
            subjectLine: "GL Funded — {$fundGl->reference}",
            content: "A GL funding request has been approved.\n\n"
                . "Amount: {$fundGl->amount}\n"
                . "Transaction ID: {$financialTransaction->id}\n"
                . "Transaction Reference: {$financialTransaction->transaction_reference}\n"
                . "GL Funding Reference: {$fundGl->reference}\n"
                . "GL Funded: {$destination?->name} ({$destination?->gl_code})\n"
                . "Requested by: {$fundGl->createdBy?->username}\n"
                . "Reviewed by: {$fundGl->reviewedBy?->username}\n"
                . "Approved by: {$actor?->username}\n",
        ));
    }
}
