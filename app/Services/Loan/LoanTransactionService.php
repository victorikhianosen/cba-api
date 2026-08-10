<?php

declare(strict_types=1);

namespace App\Services\Loan;

use App\Enums\Product\LoanProductType;
use App\Enums\Transaction\FinancialTransactionStatus;
use App\Models\FinancialTransaction;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Models\LoanTransaction;
use App\Models\Posting;
use App\Services\GeneralLedger\GeneralLedgerPostingService;
use App\Support\ReferenceGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// The only place that writes to loan_transactions. Every call applies its summary-column
// delta to the parent loan in the same DB transaction — never a full recompute from
// history. Mirrors InvestmentTransactionService's lockForUpdate + match($type) pattern.
//
// Also the only place that mirrors principal changes into the product's LOAN_PORTFOLIO
// GL — there's no Account-model-style auto-mirroring for loans (Loan isn't an Account),
// so this service does it directly via GeneralLedgerPostingService whenever
// outstanding_principal moves, exactly the same way AccountPostingService mirrors every
// balance change into an account's ACCOUNT_CONTROL GL.
class LoanTransactionService
{
    private const PORTFOLIO_MIRROR_DIRECTION = [
        'disbursement'         => 1,
        'repayment_principal'  => -1,
        'write_off'            => -1,
    ];

    public function __construct(
        private GeneralLedgerPostingService $glLedger,
    ) {}

    public function record(
        Loan $loan,
        string $type,
        string $amount,
        ?Posting $posting = null,
        ?LoanSchedule $schedule = null,
        ?string $description = null,
        bool $applyToSchedule = true,
    ): LoanTransaction {
        return DB::transaction(function () use ($loan, $type, $amount, $posting, $schedule, $description, $applyToSchedule) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            $this->applySummaryDelta($loan, $type, $amount);
            $this->mirrorLoanPortfolioGl($loan, $type, $amount, $posting);

            $transaction = $loan->transactions()->create([
                'type'                          => $type,
                'amount'                        => $amount,
                'outstanding_principal_after'   => $loan->outstanding_principal,
                'posting_id'                     => $posting?->id,
                'loan_schedule_id'               => $schedule?->id,
                'description'                    => $description,
                'created_by'                      => Auth::guard('user')->id(),
            ]);

            if ($schedule && $applyToSchedule) {
                $this->applyToScheduleRow($schedule, $type, $amount, $transaction);
            }

            return $transaction;
        });
    }

    private function applySummaryDelta(Loan $loan, string $type, string $amount): void
    {
        match ($type) {
            'disbursement' => $loan->update([
                'outstanding_principal'      => $amount,
                'total_principal_disbursed'  => bcadd((string) $loan->total_principal_disbursed, $amount, 6),
            ]),
            'repayment_principal' => $loan->update([
                'outstanding_principal'  => bcsub((string) $loan->outstanding_principal, $amount, 6),
                'total_principal_paid'    => bcadd((string) $loan->total_principal_paid, $amount, 6),
                'last_repayment_date'     => now()->toDateString(),
            ]),
            'repayment_interest' => $loan->update([
                'outstanding_interest'  => bcsub((string) $loan->outstanding_interest, $amount, 6),
                'total_interest_paid'    => bcadd((string) $loan->total_interest_paid, $amount, 6),
                'last_repayment_date'     => now()->toDateString(),
            ]),
            'repayment_fee' => $loan->update([
                'total_fees_paid'      => bcadd((string) $loan->total_fees_paid, $amount, 6),
                'last_repayment_date'   => now()->toDateString(),
            ]),
            'repayment_penalty' => $loan->update([
                'total_penalties_paid'  => bcadd((string) $loan->total_penalties_paid, $amount, 6),
                'last_repayment_date'    => now()->toDateString(),
            ]),
            'fee_charge' => $loan->update([
                'total_fees_charged' => bcadd((string) $loan->total_fees_charged, $amount, 6),
            ]),
            'penalty_accrual', 'penalty_charge' => $loan->update([
                'total_penalties_charged' => bcadd((string) $loan->total_penalties_charged, $amount, 6),
            ]),
            'write_off' => $loan->update([
                'outstanding_principal'  => '0',
                'outstanding_interest'   => '0',
                'status'                  => 'written_off',
                'written_off_at'          => now(),
            ]),
            'restructure' => null,
            'overpayment' => $loan->update([
                'overpayment_balance' => bcadd((string) $loan->overpayment_balance, $amount, 6),
            ]),
            // Suspension moves WHICH GL backs the interest (income vs suspense), not
            // whether the customer still owes it — outstanding_interest is untouched.
            'interest_suspend' => $loan->update([
                'suspended_interest_balance' => bcadd((string) $loan->suspended_interest_balance, $amount, 6),
                'interest_suspended'          => true,
                'interest_suspended_at'       => now(),
            ]),
            'interest_unsuspend' => $loan->update([
                'suspended_interest_balance' => $newSuspendedBalance = (bccomp(bcsub((string) $loan->suspended_interest_balance, $amount, 6), '0', 6) > 0
                    ? bcsub((string) $loan->suspended_interest_balance, $amount, 6)
                    : '0'),
                'interest_suspended' => bccomp($newSuspendedBalance, '0', 6) > 0,
            ]),
            default => null,
        };
    }

    // Marks the specific bucket (principal/interest/fee/penalty) this leg paid down on the
    // linked installment, then recomputes the row's overall status from all four buckets.
    private function applyToScheduleRow(LoanSchedule $schedule, string $type, string $amount, LoanTransaction $transaction): void
    {
        $column = match ($type) {
            'repayment_principal' => 'principal_paid',
            'repayment_interest'  => 'interest_paid',
            'repayment_fee'       => 'fee_paid',
            'repayment_penalty'   => 'penalty_paid',
            'penalty_accrual', 'penalty_charge' => 'penalty_due',
            default => null,
        };

        if (! $column) {
            return;
        }

        $schedule->update([
            $column                     => bcadd((string) $schedule->{$column}, $amount, 6),
            'loan_transaction_id'        => $transaction->id,
            ...(str_starts_with($column, 'penalty_due') ? [] : ['paid_date' => now()->toDateString()]),
        ]);

        $schedule->refresh();

        $fullyPaid = bccomp((string) $schedule->principal_paid, (string) $schedule->principal_due, 6) >= 0
            && bccomp((string) $schedule->interest_paid, (string) $schedule->interest_due, 6) >= 0
            && bccomp((string) $schedule->penalty_paid, (string) $schedule->penalty_due, 6) >= 0
            && bccomp((string) $schedule->fee_paid, (string) $schedule->fee_due, 6) >= 0;

        $anyPaid = bccomp((string) $schedule->principal_paid, '0', 6) > 0
            || bccomp((string) $schedule->interest_paid, '0', 6) > 0
            || bccomp((string) $schedule->penalty_paid, '0', 6) > 0
            || bccomp((string) $schedule->fee_paid, '0', 6) > 0;

        if (in_array($schedule->status, ['superseded', 'cancelled'], true)) {
            return;
        }

        $schedule->update(['status' => $fullyPaid ? 'paid' : ($anyPaid ? 'partial' : 'pending')]);
    }

    private function mirrorLoanPortfolioGl(Loan $loan, string $type, string $amount, ?Posting $posting): void
    {
        $direction = self::PORTFOLIO_MIRROR_DIRECTION[$type] ?? null;

        if (! $direction || ! $posting) {
            return;
        }

        $financialTransaction = $posting->financialTransaction;

        if (! $financialTransaction) {
            return;
        }

        $loanProduct = $loan->loanProduct()->with('generalLedgerMappings.generalLedger')->first();
        $portfolioGl = $loanProduct?->generalLedgerMappings
            ->firstWhere('financial_account_type', LoanProductType::LOAN_PORTFOLIO->value)
            ?->generalLedger;

        if (! $portfolioGl) {
            return;
        }

        $leg = [
            'financial_transaction_id' => $financialTransaction->id,
            'entry_group_id'           => (string) Str::uuid(),
            'transaction_reference'    => $financialTransaction->transaction_reference,
            'amount'                   => $amount,
            'currency_id'              => $loan->currency_id,
            'narration'                => "Loan '{$loan->account_number}' portfolio balance {$type}.",
            'created_by'               => Auth::guard('user')->id(),
            'module'                   => 'loans',
            'transaction_type'         => $type,
        ];

        // Loan Portfolio is an asset GL — a disbursement grows it (debit, asset-normal),
        // a repayment or write-off shrinks it (credit).
        $direction > 0
            ? $this->glLedger->debit($portfolioGl, $leg)
            : $this->glLedger->credit($portfolioGl, $leg);
    }

    // Moves previously-recognized interest income into the suspense GL once a loan turns
    // non-performing — a pure book entry (no customer-facing party), so it creates its own
    // FinancialTransaction the same way VirtualAccountService::creditFromInboundPayment()
    // does for its collections sweep. Not yet called from anywhere — Phase 2's
    // overdue-classification cron is what will actually trigger this once it exists; this
    // method is the capability it will call.
    public function suspendInterest(Loan $loan, string $amount, ?string $description = null): LoanTransaction
    {
        return $this->postInterestSuspenseMovement($loan, $amount, 'interest_suspend', $description);
    }

    // Reverses a suspension — e.g. the loan cures back to performing, or a repayment
    // collects previously-suspended interest.
    public function unsuspendInterest(Loan $loan, string $amount, ?string $description = null): LoanTransaction
    {
        return $this->postInterestSuspenseMovement($loan, $amount, 'interest_unsuspend', $description);
    }

    private function postInterestSuspenseMovement(Loan $loan, string $amount, string $type, ?string $description): LoanTransaction
    {
        return DB::transaction(function () use ($loan, $amount, $type, $description) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            $loanProduct = $loan->loanProduct()->with('generalLedgerMappings.generalLedger')->first();
            $interestIncomeGl = $loanProduct?->generalLedgerMappings
                ->firstWhere('financial_account_type', LoanProductType::INTEREST_ON_LOANS->value)
                ?->generalLedger;
            $suspendedInterestGl = $loanProduct?->generalLedgerMappings
                ->firstWhere('financial_account_type', LoanProductType::SUSPENDED_INTEREST->value)
                ?->generalLedger;

            if (! $interestIncomeGl || ! $suspendedInterestGl) {
                throw new \RuntimeException("Loan product '{$loanProduct?->name}' is missing INTEREST_ON_LOANS/SUSPENDED_INTEREST GL mappings.");
            }

            $actor = Auth::guard('user')->user();

            $financialTransaction = FinancialTransaction::create([
                'reference'             => ReferenceGenerator::generate('FRN'),
                'transaction_reference' => ReferenceGenerator::generate('TRN'),
                'idempotency_key'       => (string) Str::uuid(),
                'module'                => 'loans',
                'transaction_type'      => $type,
                'business_service'      => 'loans',
                'channel'               => 'system',
                'customer_id'           => $loan->customer_id,
                'amount'                => $amount,
                'fee'                    => 0,
                'commission'             => 0,
                'vat'                    => 0,
                'discount'               => 0,
                'currency_id'            => $loan->currency_id,
                'exchange_rate'          => 1,
                'status'                 => FinancialTransactionStatus::COMPLETED->value,
                'narration'              => $description ?? "Loan '{$loan->account_number}' interest {$type}.",
                'created_by'             => $actor?->id,
                'processed_at'           => now(),
                'completed_at'           => now(),
            ]);

            $leg = [
                'financial_transaction_id' => $financialTransaction->id,
                'entry_group_id'           => (string) Str::uuid(),
                'transaction_reference'    => $financialTransaction->transaction_reference,
                'amount'                   => $amount,
                'currency_id'              => $loan->currency_id,
                'narration'                => $financialTransaction->narration,
                'created_by'               => $actor?->id,
                'module'                   => 'loans',
                'transaction_type'         => $type,
            ];

            // Suspend: debit income (reverses previously recognized income), credit
            // suspense (liability, holds it until collected). Unsuspend: the reverse.
            if ($type === 'interest_suspend') {
                $this->glLedger->debit($interestIncomeGl, $leg);
                $this->glLedger->credit($suspendedInterestGl, $leg);
            } else {
                $this->glLedger->debit($suspendedInterestGl, $leg);
                $this->glLedger->credit($interestIncomeGl, $leg);
            }

            $this->applySummaryDelta($loan, $type, $amount);

            return $loan->transactions()->create([
                'type'                          => $type,
                'amount'                        => $amount,
                'outstanding_principal_after'   => $loan->outstanding_principal,
                'posting_id'                     => null,
                'loan_schedule_id'               => null,
                'description'                    => $description,
                'created_by'                      => $actor?->id,
            ]);
        });
    }
}
