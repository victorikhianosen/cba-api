<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Models\Investment;
use App\Models\InvestmentSchedule;
use App\Models\InvestmentTransaction;
use App\Models\Posting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// The only place that writes to investment_transactions. Every call applies its
// principal/summary-column delta to the parent investment in the same DB transaction —
// never a full recompute from history, per the "real-time position without
// recalculating everything" requirement.
class InvestmentTransactionService
{
    // $applyToSchedule controls whether the linked schedule row's paid_amount/status gets
    // bumped — true for an actual payment (interest_posting), false for an accrual, which
    // is a book entry only and shouldn't make the schedule installment look "paid". Either
    // way the transaction row still links to the schedule via investment_schedule_id.
    public function record(
        Investment $investment,
        string $type,
        string $amount,
        ?Posting $posting = null,
        ?InvestmentSchedule $schedule = null,
        ?string $description = null,
        bool $applyToSchedule = true,
    ): InvestmentTransaction {
        return DB::transaction(function () use ($investment, $type, $amount, $posting, $schedule, $description, $applyToSchedule) {
            $investment = Investment::whereKey($investment->id)->lockForUpdate()->firstOrFail();

            $this->applySummaryDelta($investment, $type, $amount);

            $transaction = $investment->transactions()->create([
                'type'                     => $type,
                'amount'                   => $amount,
                'principal_balance_after'  => $investment->current_principal,
                'posting_id'               => $posting?->id,
                'investment_schedule_id'   => $schedule?->id,
                'description'              => $description,
                'created_by'               => Auth::guard('user')->id(),
            ]);

            if ($schedule && $applyToSchedule) {
                $schedule->update([
                    'paid_amount' => bcadd((string) $schedule->paid_amount, $amount, 6),
                    'paid_date'   => now()->toDateString(),
                    'status'      => bccomp(bcadd((string) $schedule->paid_amount, $amount, 6), (string) $schedule->expected_interest_amount, 6) >= 0 ? 'paid' : 'partial',
                    'investment_transaction_id' => $transaction->id,
                ]);
            }

            return $transaction;
        });
    }

    private function applySummaryDelta(Investment $investment, string $type, string $amount): void
    {
        match ($type) {
            'funding' => $investment->update([
                'current_principal' => $amount,
            ]),
            'top_up' => $investment->update([
                'current_principal'   => bcadd((string) $investment->current_principal, $amount, 6),
                'total_top_up_amount' => bcadd((string) $investment->total_top_up_amount, $amount, 6),
            ]),
            'partial_withdrawal' => $investment->update([
                'current_principal'         => bcsub((string) $investment->current_principal, $amount, 6),
                'total_withdrawal_amount'   => bcadd((string) $investment->total_withdrawal_amount, $amount, 6),
            ]),
            'interest_accrual' => $investment->update([
                'total_interest_accrued' => bcadd((string) $investment->total_interest_accrued, $amount, 6),
                'outstanding_interest'    => bcadd((string) $investment->outstanding_interest, $amount, 6),
            ]),
            'interest_posting' => $investment->update([
                'total_interest_paid'        => bcadd((string) $investment->total_interest_paid, $amount, 6),
                'outstanding_interest'        => bcsub((string) $investment->outstanding_interest, $amount, 6),
                'net_interest_paid'           => bcsub(bcadd((string) $investment->total_interest_paid, $amount, 6), (string) $investment->total_withholding_tax_deducted, 6),
                'last_interest_payment_date'  => now()->toDateString(),
            ]),
            'fee' => $investment->update([
                'total_fees_charged' => bcadd((string) $investment->total_fees_charged, $amount, 6),
            ]),
            'penalty', 'early_liquidation' => $investment->update([
                'total_penalties_charged' => bcadd((string) $investment->total_penalties_charged, $amount, 6),
            ]),
            // The principal itself has left the building — whether that's an early
            // termination payout or (future) a normal maturity payout, current_principal
            // goes to zero either way. $amount is the amount actually paid out, recorded
            // as-is on the transaction row even though the resulting balance is always 0.
            'maturity_payout' => $investment->update([
                'current_principal' => '0',
            ]),
            'renewal' => null,
            default => null,
        };
    }
}
