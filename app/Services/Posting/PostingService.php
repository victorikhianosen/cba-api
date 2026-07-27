<?php

declare(strict_types=1);

namespace App\Services\Posting;

use App\Enums\Posting\PostingStatus;
use App\Enums\Transaction\FinancialTransactionStatus;
use App\Models\Account;
use App\Models\FinancialTransaction;
use App\Models\Posting;
use App\Models\User;
use App\Services\Account\AccountPostingService;
use App\Services\Audit\AuditService;
use App\Services\GeneralLedger\GeneralLedgerPostingService;
use App\Support\ReferenceGenerator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Posting → approval → ledger-write flow for all four combinations (GL↔GL, account↔account, account↔GL, GL↔account). AccountPostingService/GeneralLedgerPostingService remain the only places allowed to touch a balance — this class just resolves which one to call.
class PostingService
{
    public function __construct(
        private AccountPostingService $accountLedger,
        private GeneralLedgerPostingService $glLedger,
        private AuditService $audit,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Posting::query()->latest()->paginate($perPage)->withQueryString();
    }

    public function find(int $id): Posting
    {
        return Posting::with(['currency', 'financialTransaction'])->findOrFail($id);
    }

    // Get the details, validate them, save the posting as pending. idempotency_key is always generated here — the client never sends it.
    public function create(array $data): Posting
    {
        $data['idempotency_key'] = (string) Str::uuid();

        $this->verify($data['source_type'], $data['source_id'], $data['destination_type'], $data['destination_id'], (int) $data['currency_id'], (string) $data['amount']);

        $actor  = Auth::guard('user')->user();
        $amount = (string) $data['amount'];
        $fee    = (string) ($data['fee'] ?? 0);
        $vat    = (string) ($data['vat'] ?? 0);
        $tax    = (string) ($data['tax'] ?? 0);

        $posting = DB::transaction(fn() => Posting::create([
            ...$data,
            'reference'             => ReferenceGenerator::generate('PST'),
            'transaction_reference' => ReferenceGenerator::generate('TXN'),
            'total_amount'          => bcadd(bcadd(bcadd($amount, $fee, 4), $vat, 4), $tax, 4),
            'status'                => PostingStatus::PENDING->value,
            'created_by'            => $actor?->id,
        ])->refresh());

        $this->audit->log(
            action: 'created',
            module: 'postings',
            auditable: $posting,
            after: $posting->toArray(),
            description: "Posting '{$posting->reference}' ({$data['source_type']} → {$data['destination_type']}) was created by '{$actor?->username}'.",
        );

        return $posting;
    }

    // The atomic core — verify, debit, credit, record, all in one DB::transaction, so any failure rolls back everything and leaves the posting exactly as pending as before.
    public function approve(Posting $posting): FinancialTransaction
    {
        if ($posting->status !== PostingStatus::PENDING->value) {
            throw ValidationException::withMessages([
                'status' => ['Only pending postings can be approved.'],
            ]);
        }

        $actor = Auth::guard('user')->user();

        if ($actor && (int) $posting->created_by === (int) $actor->id) {
            throw ValidationException::withMessages([
                'status' => ['You cannot approve a posting you created yourself.'],
            ]);
        }

        $financialTransaction = DB::transaction(function () use ($posting, $actor) {
            [$source, $destination] = $this->verify(
                $posting->source_type,
                $posting->source_id,
                $posting->destination_type,
                $posting->destination_id,
                (int) $posting->currency_id,
                (string) $posting->amount,
            );

            $financialTransaction = $this->recordFinancialTransaction($posting, $source, $destination, $actor);

            $leg = [
                'financial_transaction_id' => $financialTransaction->id,
                'entry_group_id'           => (string) Str::uuid(),
                'amount'                   => $posting->amount,
                'currency_id'              => $posting->currency_id,
                'narration'                => $posting->remarks,
                'created_by'               => $actor?->id,
            ];

            $posting->source_type === 'account'
                ? $this->accountLedger->debit($source, $leg)
                : $this->glLedger->debit($source, $leg);

            $posting->destination_type === 'account'
                ? $this->accountLedger->credit($destination, $leg)
                : $this->glLedger->credit($destination, $leg);

            $posting->update([
                'status'      => PostingStatus::APPROVED->value,
                'approved_by' => $actor?->id,
                'approved_at' => now(),
            ]);

            return $financialTransaction->load(['journalEntries', 'transactions']);
        });

        $this->audit->log(
            action: 'approved',
            module: 'postings',
            auditable: $posting,
            after: ['status' => PostingStatus::APPROVED->value, 'financial_transaction_id' => $financialTransaction->id],
            description: "Posting '{$posting->reference}' was approved by '{$actor?->username}'.",
        );

        return $financialTransaction;
    }

    public function reject(Posting $posting, string $reason): Posting
    {
        if ($posting->status !== PostingStatus::PENDING->value) {
            throw ValidationException::withMessages([
                'status' => ['Only pending postings can be rejected.'],
            ]);
        }

        $actor = Auth::guard('user')->user();

        $posting->update([
            'status'           => PostingStatus::REJECTED->value,
            'rejected_by'      => $actor?->id,
            'rejected_at'      => now(),
            'rejection_reason' => $reason,
        ]);
        $posting->refresh();

        $this->audit->log(
            action: 'rejected',
            module: 'postings',
            auditable: $posting,
            after: ['status' => PostingStatus::REJECTED->value, 'rejection_reason' => $reason],
            description: "Posting '{$posting->reference}' was rejected by '{$actor?->username}': {$reason}",
        );

        return $posting;
    }

    // Resolves source/destination, locks both rows (fixed [type, id] order to avoid deadlocks), checks currency match and that the source can cover the debit.
    private function verify(string $sourceType, int $sourceId, string $destinationType, int $destinationId, int $currencyId, string $amount): array
    {
        if ($sourceType === $destinationType && $sourceId === $destinationId) {
            throw ValidationException::withMessages([
                'destination_id' => ['A posting cannot move money from a party to itself.'],
            ]);
        }

        $parties = [
            ['type' => $sourceType, 'id' => $sourceId],
            ['type' => $destinationType, 'id' => $destinationId],
        ];

        usort($parties, fn(array $a, array $b) => $a['type'] === $b['type']
            ? $a['id'] <=> $b['id']
            : $a['type'] <=> $b['type']);

        $locked = [];

        foreach ($parties as $party) {
            $class = Relation::getMorphedModel($party['type']);

            if (! $class) {
                throw ValidationException::withMessages([
                    'source_type' => ["'{$party['type']}' is not a postable party type."],
                ]);
            }

            $locked["{$party['type']}:{$party['id']}"] = $class::whereKey($party['id'])->lockForUpdate()->firstOrFail();
        }

        $source      = $locked["{$sourceType}:{$sourceId}"];
        $destination = $locked["{$destinationType}:{$destinationId}"];

        if ((int) $source->currency_id !== (int) $destination->currency_id || (int) $source->currency_id !== $currencyId) {
            throw ValidationException::withMessages([
                'currency_id' => ['Cannot post between parties with different currencies. Source, destination, and posting currency must all match.'],
            ]);
        }

        $hasSufficientBalance = $sourceType === 'account'
            ? $this->accountLedger->hasSufficientFunds($source, $amount)
            : $this->glLedger->hasSufficientBalance($source, $amount);

        if (! $hasSufficientBalance) {
            throw ValidationException::withMessages([
                'amount' => ["Posting amount exceeds the source {$sourceType}'s available balance."],
            ]);
        }

        return [$source, $destination];
    }

    private function recordFinancialTransaction(Posting $posting, Model $source, Model $destination, ?User $actor): FinancialTransaction
    {
        $customerId = match (true) {
            $source instanceof Account      => $source->customer_id,
            $destination instanceof Account => $destination->customer_id,
            default                         => null,
        };

        $financialTransaction = FinancialTransaction::create([
            'transaction_reference' => $posting->transaction_reference,
            'idempotency_key'       => $posting->idempotency_key,
            'posting_id'            => $posting->id,
            'module'                => $posting->module,
            'transaction_type'      => $posting->transaction_type,
            'transaction_subtype'   => $posting->transaction_subtype,
            'business_service'      => $posting->business_service,
            'channel'               => $posting->channel,
            'customer_id'           => $customerId,
            'amount'                => $posting->amount,
            'fee'                   => $posting->fee,
            'commission'            => 0,
            'vat'                   => $posting->vat,
            'discount'              => 0,
            'currency_id'           => $posting->currency_id,
            'exchange_rate'         => $posting->exchange_rate,
            'status'                => FinancialTransactionStatus::COMPLETED->value,
            'narration'             => $posting->remarks,
            'created_by'            => $actor?->id,
            'processed_at'          => now(),
            'completed_at'          => now(),
        ]);

        Log::channel('transactions')->info('financial_transaction', [
            'financial_transaction_id' => $financialTransaction->id,
            'transaction_reference'    => $financialTransaction->transaction_reference,
            'posting_id'               => $posting->id,
            'reference'                => $posting->reference,
            'source_type'              => $posting->source_type,
            'source_id'                => $posting->source_id,
            'destination_type'         => $posting->destination_type,
            'destination_id'           => $posting->destination_id,
            'amount'                   => $financialTransaction->amount,
            'fee'                      => $financialTransaction->fee,
            'vat'                      => $financialTransaction->vat,
            'currency_id'              => $financialTransaction->currency_id,
            'status'                   => $financialTransaction->status,
            'created_by'               => $actor?->id,
        ]);

        return $financialTransaction;
    }




}
