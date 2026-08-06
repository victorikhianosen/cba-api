<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Enums\Investment\InvestmentPartialLiquidationStatus;
use App\Enums\Product\InvestmentProductType;
use App\Models\Investment;
use App\Models\InvestmentPartialLiquidation;
use App\Models\InvestmentProduct;
use App\Services\Audit\AuditService;
use App\Services\Notification\NotificationService;
use App\Services\Posting\PostingService;
use App\Support\ReferenceGenerator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// A dedicated maker→approver (2-step, TOTP-gated on approve) workflow for withdrawing
// PART of an active investment's current_principal early — distinct from the top-up
// flow's single-pending-slot-on-Investment pattern because this needs real history
// (list all pending/approved/rejected requests), same reasoning as the Fund GL table.
// Full-principal withdrawal is InvestmentService::terminate() — this is strictly for
// amounts less than the current principal.
class InvestmentPartialLiquidationService
{
    public function __construct(
        private PostingService $postings,
        private InvestmentTransactionService $transactions,
        private InvestmentScheduleService $schedules,
        private AuditService $audit,
        private NotificationService $notifications,
    ) {}

    public function list(int $perPage = 15, ?string $from = null, ?string $to = null, ?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        return $this->filtered($from, $to, $search, $status)
            ->with(['investment.customer', 'requestedBy', 'approvedBy', 'rejectedBy'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    private function filtered(?string $from, ?string $to, ?string $search = null, ?string $status = null): Builder
    {
        return InvestmentPartialLiquidation::query()
            ->when($from, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('investment', fn ($q) => $q->where('account_number', 'like', "%{$search}%"));
                });
            });
    }

    public function find(int $id): InvestmentPartialLiquidation
    {
        return InvestmentPartialLiquidation::with([
            'investment.customer', 'requestedBy', 'approvedBy', 'rejectedBy', 'investmentTransaction',
        ])->findOrFail($id);
    }

    // Guards: investment must be active, the product must allow partial withdrawal, the
    // amount must be strictly between 0 and current_principal (never the full amount —
    // that's what terminate() is for), and only one PENDING request may be open at a time
    // for a given investment.
    public function request(Investment $investment, string $amount, string $reason): InvestmentPartialLiquidation
    {
        if ($investment->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => ['Only active investments can be partially liquidated.'],
            ]);
        }

        $product = InvestmentProduct::findOrFail($investment->investment_product_id);

        if (! $product->allow_partial_withdrawal) {
            throw ValidationException::withMessages([
                'status' => ['This investment product does not allow partial withdrawal.'],
            ]);
        }

        if (bccomp($amount, '0', 6) <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['The amount must be greater than zero.'],
            ]);
        }

        if (bccomp($amount, (string) $investment->current_principal, 6) >= 0) {
            throw ValidationException::withMessages([
                'amount' => ['The amount must be less than the investment\'s current principal — use termination to withdraw the full amount.'],
            ]);
        }

        $this->assertWithinRetentionLimit($investment, $amount);

        $hasPending = InvestmentPartialLiquidation::query()
            ->where('investment_id', $investment->id)
            ->where('status', InvestmentPartialLiquidationStatus::PENDING->value)
            ->exists();

        if ($hasPending) {
            throw ValidationException::withMessages([
                'status' => ['This investment already has a partial liquidation request pending approval.'],
            ]);
        }

        $actor = Auth::guard('user')->user();

        $liquidation = DB::transaction(fn () => InvestmentPartialLiquidation::create([
            'investment_id' => $investment->id,
            'reference'     => ReferenceGenerator::generate('IPL'),
            'amount'        => $amount,
            'reason'        => $reason,
            'status'        => InvestmentPartialLiquidationStatus::PENDING->value,
            'requested_by'  => $actor?->id,
        ])->refresh());

        $this->audit->log(
            action: 'created',
            module: 'investment_partial_liquidations',
            auditable: $liquidation,
            after: $liquidation->toArray(),
            description: "A partial liquidation of {$amount} was requested for investment '{$investment->account_number}' by '{$actor?->username}'.",
        );

        $this->notifications->notifyPermission(
            'approve_investments',
            'investment_partial_liquidation',
            $liquidation->id,
            'New partial liquidation request',
            "A partial liquidation of {$amount} for investment '{$investment->account_number}' is awaiting your approval.",
        );

        return $liquidation;
    }

    // Approving IS the authorization to move the money — same single-step model as
    // terminate()/approveTopUp(). Cashiers are blocked (consistent with Fund GL), and the
    // approver must differ from the requester.
    public function approve(InvestmentPartialLiquidation $liquidation): InvestmentPartialLiquidation
    {
        if ($liquidation->status !== InvestmentPartialLiquidationStatus::PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Only pending partial liquidation requests can be approved.'],
            ]);
        }

        $actor = Auth::guard('user')->user();

        if ($actor?->hasRole('cashier')) {
            throw ValidationException::withMessages([
                'status' => ['Cashiers cannot approve partial liquidation requests.'],
            ]);
        }

        if ($actor && (int) $liquidation->requested_by === (int) $actor->id) {
            throw ValidationException::withMessages([
                'status' => ['You cannot approve a partial liquidation request you requested yourself.'],
            ]);
        }

        $liquidation = DB::transaction(function () use ($liquidation, $actor) {
            $locked = InvestmentPartialLiquidation::whereKey($liquidation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== InvestmentPartialLiquidationStatus::PENDING) {
                throw ValidationException::withMessages([
                    'status' => ['Only pending partial liquidation requests can be approved.'],
                ]);
            }

            $investment = Investment::whereKey($liquidation->investment_id)->lockForUpdate()->firstOrFail();

            if ($investment->status !== 'active') {
                throw ValidationException::withMessages([
                    'status' => ['Only active investments can be partially liquidated.'],
                ]);
            }

            $amount = (string) $liquidation->amount;

            if (bccomp($amount, (string) $investment->current_principal, 6) >= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['The requested amount is no longer less than the investment\'s current principal.'],
                ]);
            }

            // Re-check the retention floor under lock — current_principal may have changed
            // (e.g. via a top-up) between the original request and this approval.
            $this->assertWithinRetentionLimit($investment, $amount);

            $product = InvestmentProduct::with('generalLedgerMappings')->findOrFail($investment->investment_product_id);

            $fundingMapping = $product->generalLedgerMappings
                ->firstWhere('financial_account_type_name', InvestmentProductType::INVESTMENT_FUNDING->name);

            if (! $fundingMapping) {
                throw ValidationException::withMessages([
                    'investment_product_id' => ['This investment product has no Investment Funding GL mapping configured.'],
                ]);
            }

            $payoutTransaction = $this->postings->createAndApprove([
                'source_type'      => 'general_ledger',
                'source_id'        => $fundingMapping->general_ledger_id,
                'destination_type' => 'account',
                'destination_id'   => $investment->source_account_id,
                'amount'           => $amount,
                'module'           => 'investments',
                'transaction_type' => 'investment_partial_liquidation_payout',
                'remarks'          => "Partial liquidation payout for investment '{$investment->account_number}': {$liquidation->reason}",
            ]);

            $investmentTransaction = $this->transactions->record(
                investment: $investment,
                type: 'partial_withdrawal',
                amount: $amount,
                posting: $payoutTransaction->posting,
                description: "Partial liquidation via posting '{$payoutTransaction->posting?->reference}'.",
            );

            $investment = $investment->refresh();

            $this->schedules->regenerateRemaining($investment);

            $liquidation->update([
                'status'                    => InvestmentPartialLiquidationStatus::APPROVED->value,
                'approved_by'               => $actor?->id,
                'approved_at'               => now(),
                'investment_transaction_id' => $investmentTransaction->id,
                // Denormalized off the payout's FinancialTransaction (TRN...) so this
                // liquidation can be traced straight to its ledger movement without a join.
                'transaction_reference'     => $payoutTransaction->transaction_reference,
            ]);

            return $liquidation->refresh();
        });

        $this->audit->log(
            action: 'approved',
            module: 'investment_partial_liquidations',
            auditable: $liquidation,
            after: ['status' => $liquidation->status, 'investment_transaction_id' => $liquidation->investment_transaction_id],
            description: "Partial liquidation request '{$liquidation->reference}' was approved by '{$actor?->username}' and applied immediately.",
        );

        $this->notifications->notifyUser(
            $liquidation->requestedBy,
            'investment_partial_liquidation',
            $liquidation->id,
            'Partial liquidation approved',
            "Your partial liquidation request '{$liquidation->reference}' for {$liquidation->amount} was approved.",
        );

        return $liquidation;
    }

    public function reject(InvestmentPartialLiquidation $liquidation, string $reason): InvestmentPartialLiquidation
    {
        $actor = Auth::guard('user')->user();

        $liquidation = DB::transaction(function () use ($liquidation, $reason, $actor) {
            $locked = InvestmentPartialLiquidation::whereKey($liquidation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== InvestmentPartialLiquidationStatus::PENDING) {
                throw ValidationException::withMessages([
                    'status' => ['Only pending partial liquidation requests can be rejected.'],
                ]);
            }

            $liquidation->update([
                'status'           => InvestmentPartialLiquidationStatus::REJECTED->value,
                'rejected_by'      => $actor?->id,
                'rejected_at'      => now(),
                'rejection_reason' => $reason,
            ]);

            return $liquidation->refresh();
        });

        $this->audit->log(
            action: 'rejected',
            module: 'investment_partial_liquidations',
            auditable: $liquidation,
            after: ['status' => $liquidation->status, 'rejection_reason' => $reason],
            description: "Partial liquidation request '{$liquidation->reference}' was rejected by '{$actor?->username}': {$reason}",
        );

        $this->notifications->notifyUser(
            $liquidation->requestedBy,
            'investment_partial_liquidation',
            $liquidation->id,
            'Partial liquidation rejected',
            "Your partial liquidation request '{$liquidation->reference}' was rejected: {$reason}",
        );

        return $liquidation;
    }

    // Caps how much of current_principal a single partial liquidation request may take,
    // per the investment's (product-inherited, booking-time-frozen) minimum retention
    // floor. No floor configured means no cap. bcmath throughout to match how the rest of
    // this service does money math.
    private function assertWithinRetentionLimit(Investment $investment, string $amount): void
    {
        if ($investment->minimum_retention_percentage === null) {
            return;
        }

        $minRemaining = bcmul((string) $investment->current_principal, (string) ($investment->minimum_retention_percentage / 100), 6);
        $maxLiquidation = bcsub((string) $investment->current_principal, $minRemaining, 6);

        if (bccomp($amount, $maxLiquidation, 6) > 0) {
            throw ValidationException::withMessages([
                'amount' => ["Amount exceeds the maximum liquidatable amount of {$maxLiquidation} — this investment must retain at least {$investment->minimum_retention_percentage}% of its current principal."],
            ]);
        }
    }
}
