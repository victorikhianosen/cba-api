<?php

declare(strict_types=1);

namespace App\Services\Loan;

use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Services\Report\CronReportService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// The single home for every loan-related scheduled job — currently just the daily
// overdue pass, invoked via `loans:process-overdue` (see LoanCronJobCommand, which holds
// no logic of its own and only calls process() below). Mirrors
// InvestmentInterestProcessingService's shape exactly: chunked (never loads the whole
// table), one DB transaction per schedule row (a failure on one loan never blocks or
// rolls back the rest of the batch), idempotent re-runs (accrued_at guards against
// double-accrual within the same day).
//
// Two passes, same reasoning as documented when this was scoped:
//   1. accrue() — walks every overdue installment, books daily penalty interest against
//      it (skipping installments still within the product's arrears tolerance).
//   2. reclassify() — recomputes days_in_arrears/npl_classification ONCE per loan touched
//      (not once per overdue installment), then auto-suspends/unsuspends interest as a
//      loan crosses the performing <-> non-performing boundary. This is what actually
//      activates the suspendInterest()/unsuspendInterest() plumbing that existed but was
//      never triggered by anything.
class LoanCronJobService
{
    private const CHUNK_SIZE = 200;

    public function __construct(
        private LoanTransactionService $transactions,
        private LoanClassificationRuleService $classifier,
        private CronReportService $reports,
    ) {}

    /**
     * @return array{accrued: int, reclassified: int, failed: int, dry_run_lines: string[], items: array}
     */
    public function process(bool $dryRun = false): array
    {
        $startedAt   = now();
        $accrued     = 0;
        $failed      = 0;
        $dryRunLines = [];
        $items       = [];
        $touchedLoanIds = [];

        LoanSchedule::query()
            ->whereIn('status', ['pending', 'partial'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->where(function ($query) {
                $query->whereNull('accrued_at')
                    ->orWhereDate('accrued_at', '<', now()->toDateString());
            })
            ->whereHas('loan', fn ($query) => $query->where('status', 'active'))
            ->with('loan.loanProduct.generalLedgerMappings.generalLedger')
            ->chunkById(self::CHUNK_SIZE, function ($schedules) use (&$accrued, &$failed, &$dryRunLines, &$items, &$touchedLoanIds, $dryRun) {
                foreach ($schedules as $schedule) {
                    try {
                        if ($dryRun) {
                            $dryRunLines[] = "Would accrue penalty for loan schedule #{$schedule->id} (loan #{$schedule->loan_id}).";
                            $touchedLoanIds[$schedule->loan_id] = true;
                            continue;
                        }

                        $row = $this->accrue($schedule);
                        if ($row) {
                            $accrued++;
                            $items[] = [
                                'label'     => "Penalty Accrual — Loan {$row['loan_account']}",
                                'status'    => 'success',
                                'amount'    => $row['amount'],
                                'reference' => $row['reference'],
                                'detail'    => $row['gl'],
                            ];
                        }
                        $touchedLoanIds[$schedule->loan_id] = true;
                    } catch (\Throwable $e) {
                        $failed++;
                        $items[] = [
                            'label'     => "Penalty Accrual — Loan #{$schedule->loan_id}",
                            'status'    => 'failed',
                            'amount'    => null,
                            'reference' => null,
                            'detail'    => $e->getMessage(),
                        ];
                        Log::channel('loans')->error('loan_overdue_accrual_failed', [
                            'loan_schedule_id' => $schedule->id,
                            'loan_id'          => $schedule->loan_id,
                            'message'          => $e->getMessage(),
                        ]);
                    }
                }
            });

        $reclassified = 0;

        if (! $dryRun) {
            foreach (array_keys($touchedLoanIds) as $loanId) {
                try {
                    $row = $this->reclassify((int) $loanId);
                    if ($row) {
                        $reclassified++;
                        $items[] = [
                            'label'     => "{$row['stage']} — Loan {$row['loan_account']}",
                            'status'    => 'success',
                            'amount'    => $row['amount'],
                            'reference' => $row['reference'],
                            'detail'    => $row['gl'],
                        ];
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $items[] = [
                        'label'     => "NPL Reclassification — Loan #{$loanId}",
                        'status'    => 'failed',
                        'amount'    => null,
                        'reference' => null,
                        'detail'    => $e->getMessage(),
                    ];
                    Log::channel('loans')->error('loan_reclassification_failed', [
                        'loan_id' => $loanId,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        $finishedAt = now();

        $this->reports->send(
            jobName: 'Loan Cron Job',
            jobKey: 'loans-process-overdue',
            items: $items,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
            dryRun: $dryRun,
        );

        return [
            'accrued'       => $accrued,
            'reclassified'  => $reclassified,
            'failed'        => $failed,
            'dry_run_lines' => $dryRunLines,
            'items'         => $items,
        ];
    }

    /**
     * @return array{stage: string, loan_account: string, customer_account: string, gl: string, amount: string, reference: string}|null
     */
    private function accrue(LoanSchedule $schedule): ?array
    {
        return DB::transaction(function () use ($schedule) {
            $locked = LoanSchedule::whereKey($schedule->id)->lockForUpdate()->firstOrFail();

            // Another run (or the same run's previous chunk) already accrued today —
            // skip, don't double-book.
            if ($locked->accrued_at && Carbon::parse($locked->accrued_at)->isSameDay(now())) {
                return null;
            }

            $loan = Loan::whereKey($locked->loan_id)->lockForUpdate()->firstOrFail();

            $overdueAmount = bcadd(
                bcsub((string) $locked->principal_due, (string) $locked->principal_paid, 6),
                bcsub((string) $locked->interest_due, (string) $locked->interest_paid, 6),
                6,
            );

            if (bccomp($overdueAmount, '0', 6) <= 0) {
                $locked->update(['accrued_at' => now()]);
                return null;
            }

            // Within the product's tolerance — don't charge a penalty for a rounding-scale
            // shortfall, but still stamp accrued_at so this row isn't re-evaluated all day.
            if (bccomp($overdueAmount, (string) $loan->arrears_tolerance_amount, 2) <= 0) {
                $locked->update(['accrued_at' => now()]);
                return null;
            }

            if (bccomp((string) $loan->penalty_interest_rate, '0', 6) <= 0) {
                $locked->update(['accrued_at' => now()]);
                return null;
            }

            // One day's penalty interest on the overdue amount — annualized rate / 365.
            $dailyPenalty = bcdiv(
                bcmul($overdueAmount, (string) $loan->penalty_interest_rate, 10),
                bcmul('365', '100', 10),
                2,
            );

            if (bccomp($dailyPenalty, '0', 2) <= 0) {
                $locked->update(['accrued_at' => now()]);
                return null;
            }

            $transaction = $this->transactions->record(
                loan: $loan,
                type: 'penalty_accrual',
                amount: $dailyPenalty,
                schedule: $locked,
                description: "Daily penalty accrual on overdue installment #{$locked->installment_number}.",
            );

            $locked->update(['accrued_at' => now()]);

            // Penalty accrual is a book entry on the loan's own running totals only — it
            // doesn't debit/credit any customer account or GL (that only happens when the
            // penalty is actually repaid, via LoanRepaymentService), so that's reported
            // honestly rather than implying a movement that didn't happen.
            return [
                'stage'            => 'Penalty Accrual',
                'loan_account'     => $loan->account_number,
                'customer_account' => '— (no account movement)',
                'gl'               => 'Book entry only — no GL/account movement',
                'amount'           => $dailyPenalty,
                'reference'        => $transaction->reference,
            ];
        });
    }

    // Recomputes days_in_arrears (the oldest unpaid installment's age, in days) and
    // npl_classification for one loan, then auto-suspends/unsuspends interest as it
    // crosses the performing <-> non-performing boundary. Only writes when something
    // actually changed, so a stable loan doesn't get touched on every run.
    //
    // @return array{stage: string, loan_account: string, customer_account: string, gl: string, amount: string, reference: string}|null
    private function reclassify(int $loanId): ?array
    {
        return DB::transaction(function () use ($loanId) {
            $loan = Loan::whereKey($loanId)->lockForUpdate()->firstOrFail();

            $oldestOverdueDate = $loan->schedules()
                ->whereIn('status', ['pending', 'partial'])
                ->whereDate('due_date', '<', now()->toDateString())
                ->min('due_date');

            $daysInArrears = $oldestOverdueDate
                ? max(0, (int) Carbon::parse($oldestOverdueDate)->diffInDays(now()->startOfDay()))
                : 0;

            $rule = $this->classifier->classify($daysInArrears);
            $newClassification = $rule?->name ?? 'performing';
            $oldClassification = $loan->npl_classification;

            $changed = $daysInArrears !== $loan->days_in_arrears || $newClassification !== $oldClassification;

            if (! $changed) {
                return null;
            }

            $loan->update([
                'days_in_arrears'    => $daysInArrears,
                'npl_classification' => $newClassification,
                'npl_classified_at'  => now(),
            ]);

            // Crossed INTO non-performing (any bucket beyond "performing") — suspend
            // whatever interest income has been recognized but isn't confidently
            // collectible anymore. This IS a real GL movement (Interest on Loans ->
            // Suspended Interest), unlike penalty accrual above — no customer account is
            // a party to it either way (it's an internal income-recognition entry).
            $glMovement = "Classification changed ({$oldClassification} -> {$newClassification}) — no interest to suspend/release";
            $amount     = '0.00';
            $reference  = null;

            if ($newClassification !== 'performing' && ! $loan->interest_suspended && bccomp((string) $loan->outstanding_interest, '0', 2) > 0) {
                $transaction = $this->transactions->suspendInterest(
                    $loan,
                    (string) $loan->outstanding_interest,
                    "Auto-suspended: loan reclassified to '{$rule?->label}' ({$daysInArrears} days in arrears).",
                );
                $glMovement = 'Interest on Loans -> Suspended Interest';
                $amount     = $transaction->amount;
                $reference  = $transaction->transaction_reference ?? $transaction->reference;
            }

            // Cured back to performing — release the suspended balance back to income.
            if ($newClassification === 'performing' && $loan->interest_suspended && bccomp((string) $loan->suspended_interest_balance, '0', 2) > 0) {
                $transaction = $this->transactions->unsuspendInterest(
                    $loan,
                    (string) $loan->suspended_interest_balance,
                    'Auto-unsuspended: loan cured back to performing.',
                );
                $glMovement = 'Suspended Interest -> Interest on Loans';
                $amount     = $transaction->amount;
                $reference  = $transaction->transaction_reference ?? $transaction->reference;
            }

            return [
                'stage'            => "NPL Reclassification ({$oldClassification} -> {$newClassification})",
                'loan_account'     => $loan->account_number,
                'customer_account' => '— (no account movement)',
                'gl'               => $glMovement,
                'amount'           => $amount,
                'reference'        => $reference,
            ];
        });
    }
}
