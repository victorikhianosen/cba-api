<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Enums\Product\InvestmentProductType;
use App\Models\InvestmentProduct;
use App\Models\InvestmentSchedule;
use App\Services\Posting\PostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// The actual work behind the `investments:process-interest` cron — accrues and pays out
// interest for every schedule installment that's fallen due. Kept as a service (not inline
// in the command) so the logic is directly unit-testable and callable from anywhere else
// that might need it (an admin "run now" button, a queued job, etc.), not just artisan.
//
// Two passes: accrue() books the P&L/liability entry (Dr Interest Expense / Cr Interest
// Payable) the day an installment becomes due, payout() then clears that liability and pays
// the customer (Dr Interest Payable / Cr customer account). Built to survive real volume:
// chunked queries (never loads the whole table), one DB transaction per schedule row (a
// failure on one investment never blocks or rolls back the rest of the batch), and
// idempotent re-runs (accrued_at / status guards mean a retry after a crash can't double-post).
class InvestmentInterestProcessingService
{
    private const CHUNK_SIZE = 200;

    public function __construct(
        private PostingService $postings,
        private InvestmentTransactionService $transactions,
    ) {}

    /**
     * @return array{accrued: int, paid: int, failed: int, dry_run_lines: string[]}
     */
    public function process(bool $dryRun = false): array
    {
        $accrued      = 0;
        $paid         = 0;
        $failed       = 0;
        $dryRunLines  = [];

        InvestmentSchedule::query()
            ->whereNull('accrued_at')
            ->whereDate('due_date', '<=', now()->toDateString())
            ->whereHas('investment', fn ($query) => $query->where('status', 'active'))
            ->with('investment.investmentProduct.generalLedgerMappings')
            ->chunkById(self::CHUNK_SIZE, function ($schedules) use (&$accrued, &$failed, &$dryRunLines, $dryRun) {
                foreach ($schedules as $schedule) {
                    try {
                        if ($dryRun) {
                            $dryRunLines[] = "Would accrue {$schedule->expected_interest_amount} for schedule #{$schedule->id}.";
                            continue;
                        }

                        $this->accrue($schedule);
                        $accrued++;
                    } catch (\Throwable $e) {
                        $failed++;
                        report($e);
                        Log::channel('transactions')->error('investment_interest_accrual_failed', [
                            'investment_schedule_id' => $schedule->id,
                            'error'                  => $e->getMessage(),
                        ]);
                    }
                }
            });

        InvestmentSchedule::query()
            ->whereNotNull('accrued_at')
            ->whereIn('status', ['pending', 'partial'])
            ->whereDate('due_date', '<=', now()->toDateString())
            ->whereHas('investment', fn ($query) => $query->where('status', 'active'))
            ->with('investment.investmentProduct.generalLedgerMappings', 'investment.sourceAccount')
            ->chunkById(self::CHUNK_SIZE, function ($schedules) use (&$paid, &$failed, &$dryRunLines, $dryRun) {
                foreach ($schedules as $schedule) {
                    try {
                        $remaining = bcsub((string) $schedule->expected_interest_amount, (string) $schedule->paid_amount, 6);

                        if (bccomp($remaining, '0', 6) <= 0) {
                            continue;
                        }

                        if ($dryRun) {
                            $dryRunLines[] = "Would pay out {$remaining} for schedule #{$schedule->id}.";
                            continue;
                        }

                        $this->payout($schedule, $remaining);
                        $paid++;
                    } catch (\Throwable $e) {
                        $failed++;
                        report($e);
                        Log::channel('transactions')->error('investment_interest_payout_failed', [
                            'investment_schedule_id' => $schedule->id,
                            'error'                  => $e->getMessage(),
                        ]);
                    }
                }
            });

        return [
            'accrued'       => $accrued,
            'paid'          => $paid,
            'failed'        => $failed,
            'dry_run_lines' => $dryRunLines,
        ];
    }

    private function accrue(InvestmentSchedule $schedule): void
    {
        DB::transaction(function () use ($schedule) {
            $locked = InvestmentSchedule::whereKey($schedule->id)->lockForUpdate()->firstOrFail();

            if ($locked->accrued_at !== null) {
                return; // already handled by a concurrent/previous run
            }

            $investment = $schedule->investment;
            $mappings   = $this->mappingsFor($investment->investmentProduct, [
                InvestmentProductType::INTEREST_EXPENSE,
                InvestmentProductType::INTEREST_PAYABLE,
            ]);

            $financialTransaction = $this->postings->createAndApprove([
                'source_type'      => 'general_ledger',
                'source_id'        => $mappings[InvestmentProductType::INTEREST_EXPENSE->name],
                'destination_type' => 'general_ledger',
                'destination_id'   => $mappings[InvestmentProductType::INTEREST_PAYABLE->name],
                'amount'           => (string) $schedule->expected_interest_amount,
                'module'           => 'investments',
                'transaction_type' => 'investment_interest_accrual',
                'remarks'          => "Interest accrual for investment '{$investment->account_number}', installment #{$schedule->installment_number}.",
            ]);

            $this->transactions->record(
                investment: $investment,
                type: 'interest_accrual',
                amount: (string) $schedule->expected_interest_amount,
                posting: $financialTransaction->posting,
                schedule: $schedule,
                description: "Accrued via posting '{$financialTransaction->posting?->reference}'.",
                applyToSchedule: false,
            );

            $locked->update(['accrued_at' => now()]);
        });
    }

    private function payout(InvestmentSchedule $schedule, string $remaining): void
    {
        DB::transaction(function () use ($schedule, $remaining) {
            $locked = InvestmentSchedule::whereKey($schedule->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['pending', 'partial'], true)) {
                return; // already paid by a concurrent/previous run
            }

            $investment = $schedule->investment;
            $mappings   = $this->mappingsFor($investment->investmentProduct, [
                InvestmentProductType::INTEREST_PAYABLE,
            ]);

            // Textbook interest-payment entry: debit Interest Payable (clears the accrued
            // liability), credit the customer's account (they receive the cash) — one
            // posting. (An earlier version of this routed through the Interest Paid GL as
            // an intermediate "clearing" leg, but this ledger engine's debit=increase/
            // credit=decrease polarity for asset GLs means that leg actually drove Interest
            // Paid negative instead of funding it — a real double-entry payment doesn't need
            // a middle GL between the liability and the payee's account anyway.)
            $payoutTransaction = $this->postings->createAndApprove([
                'source_type'      => 'general_ledger',
                'source_id'        => $mappings[InvestmentProductType::INTEREST_PAYABLE->name],
                'destination_type' => 'account',
                'destination_id'   => $investment->source_account_id,
                'amount'           => $remaining,
                'module'           => 'investments',
                'transaction_type' => 'investment_interest_payout',
                'remarks'          => "Interest payout for investment '{$investment->account_number}', installment #{$schedule->installment_number}.",
            ]);

            $this->transactions->record(
                investment: $investment,
                type: 'interest_posting',
                amount: $remaining,
                posting: $payoutTransaction->posting,
                schedule: $locked,
                description: "Interest paid via posting '{$payoutTransaction->posting?->reference}'.",
            );
        });
    }

    /**
     * @param InvestmentProductType[] $roles
     * @return array<string, int>
     */
    private function mappingsFor(InvestmentProduct $product, array $roles): array
    {
        $mappings = $product->generalLedgerMappings->pluck('general_ledger_id', 'financial_account_type_name');

        foreach ($roles as $role) {
            if (! $mappings->has($role->name)) {
                throw new \RuntimeException("Investment product '{$product->name}' has no {$role->label()} GL mapping configured.");
            }
        }

        return $mappings->all();
    }
}
