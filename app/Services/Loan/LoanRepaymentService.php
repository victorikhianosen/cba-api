<?php

declare(strict_types=1);

namespace App\Services\Loan;

use App\Enums\Product\LoanProductType;
use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanSchedule;
use App\Models\LoanTransaction;
use App\Services\Posting\PostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Allocates a repayment across outstanding installments, oldest-due-first, in a
// per-product-configurable bucket order (Loan::DEFAULT_PAYMENT_ALLOCATION_ORDER =
// Penalty -> Fee -> Interest -> Principal when a product hasn't set its own). Default
// reasoning:
//   1. Penalties are cleared first — they represent delinquency risk/cost; if principal
//      were repaid before penalties, a serially-late borrower could keep "curing" their
//      visible principal balance every month while a stack of unpaid penalties quietly
//      grows off to the side, understating real arrears.
//   2. Fees next — administrative/service fees are contractually due before interest in
//      almost every standard loan-servicing waterfall (Fineract/Mambu default order,
//      recognizable to a CBN examiner).
//   3. Interest before principal — the universal amortized-loan convention: the lender's
//      earned yield is realized before principal reduction, protecting both income
//      recognition and accurate risk exposure reporting.
//   4. Principal absorbs whatever remains — by construction the lowest priority, since a
//      shortfall here is exactly what should surface as arrears/partial status.
// A product can override this order (see LoanProduct::payment_allocation_order), snapshotted
// onto the loan at booking — e.g. a product might want Fee -> Interest -> Penalty -> Principal.
//
// Repayment itself is instant/TOTP-gated, not maker-checker: disbursement is the bank
// extending new credit and deserves the heavier booking->approval gate, but a repayment
// is a teller/cashier processing money the customer has already handed over in the
// moment — mirrors real branch banking, where a loan officer approves the loan once but
// a teller processes each repayment as it happens.
class LoanRepaymentService
{
    public function __construct(
        private PostingService $postings,
        private LoanTransactionService $transactions,
    ) {}

    /**
     * Pure allocation preview — shows how a hypothetical repayment would be split across
     * installments/buckets without posting or persisting anything.
     *
     * @return array{amount: string, allocations: array<int, array<string, mixed>>, unallocated: string}
     */
    public function preview(Loan $loan, string $amount): array
    {
        $schedules = $loan->schedules()
            ->whereIn('status', ['pending', 'partial'])
            ->orderBy('installment_number')
            ->get();

        $order = $loan->payment_allocation_order ?: Loan::DEFAULT_PAYMENT_ALLOCATION_ORDER;

        [$allocations, $remaining] = $this->allocate($schedules, $amount, $order);

        return [
            'amount'       => $amount,
            'order'        => $order,
            'allocations'  => $allocations,
            'unallocated'  => $remaining,
        ];
    }

    public function record(Loan $loan, string $amount, int $fundingAccountId, ?string $description = null): array
    {
        if ($loan->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => ['Only active loans can receive repayments.'],
            ]);
        }

        if (bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Repayment amount must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use ($loan, $amount, $fundingAccountId, $description) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            $fundingAccount = Account::findOrFail($fundingAccountId);

            if ((int) $fundingAccount->currency_id !== (int) $loan->currency_id) {
                throw ValidationException::withMessages([
                    'funding_account_id' => ['The funding account currency does not match the loan currency.'],
                ]);
            }

            $schedules = $loan->schedules()
                ->whereIn('status', ['pending', 'partial'])
                ->orderBy('installment_number')
                ->get();

            $order = $loan->payment_allocation_order ?: Loan::DEFAULT_PAYMENT_ALLOCATION_ORDER;

            [$allocations, $remaining] = $this->allocate($schedules, $amount, $order);

            $product = $loan->loanProduct()->with('generalLedgerMappings.generalLedger')->first();
            $transactions = [];

            // Bucket -> [GL role, transaction type] — posted in the loan's configured
            // order so the transaction log reads in the order money was actually applied.
            $bucketConfig = [
                'penalty'   => ['INCOME_FROM_PENALTIES', 'repayment_penalty'],
                'fee'       => ['INCOME_FROM_FEES', 'repayment_fee'],
                'interest'  => ['INTEREST_ON_LOANS', 'repayment_interest'],
                // Customer-facing posting replenishes FUND_SOURCE (the loanable-funds
                // pool disbursement drew down from) — LOAN_PORTFOLIO is never a party in
                // this posting; LoanTransactionService::record() mirrors the
                // portfolio-balance decrease as a separate, parallel book entry tied to
                // the same FinancialTransaction.
                'principal' => ['FUND_SOURCE', 'repayment_principal'],
            ];

            foreach ($allocations as $allocation) {
                foreach ($order as $bucket) {
                    if (! isset($bucketConfig[$bucket]) || bccomp($allocation[$bucket], '0', 2) <= 0) {
                        continue;
                    }

                    [$glRole, $transactionType] = $bucketConfig[$bucket];
                    $transactions[] = $this->postLeg($loan, $product, $fundingAccount, $allocation[$bucket], $glRole, $transactionType, $allocation['schedule'], $description);

                    // A collected interest payment on a suspended loan means that portion
                    // of interest is now real, recognized income (already correctly
                    // credited to INTEREST_ON_LOANS by the postLeg() above) — so it's no
                    // longer "suspended", just a tracking-balance decrement here, not a
                    // second GL movement (that would double-count the income already
                    // recognized by the repayment posting itself).
                    if ($bucket === 'interest' && $loan->interest_suspended) {
                        $cleared = bccomp($allocation['interest'], (string) $loan->suspended_interest_balance, 6) >= 0
                            ? (string) $loan->suspended_interest_balance
                            : $allocation['interest'];
                        $newBalance = bcsub((string) $loan->suspended_interest_balance, $cleared, 6);
                        $loan->update([
                            'suspended_interest_balance' => $newBalance,
                            'interest_suspended'          => bccomp($newBalance, '0', 6) > 0,
                        ]);
                        $loan->refresh();
                    }
                }
            }

            // Anything left after every currently-due/overdue installment is fully
            // cleared is a prepayment beyond what's owed today — routed to the
            // OVERPAYMENT GL/summary bucket rather than silently discarded or forced
            // onto a not-yet-due installment.
            if (bccomp($remaining, '0', 2) > 0) {
                $transactions[] = $this->postLeg($loan, $product, $fundingAccount, $remaining, 'OVERPAYMENT', 'overpayment', null, $description);
            }

            return [
                'loan'          => $loan->refresh(),
                'transactions'  => $transactions,
            ];
        });
    }

    /**
     * @param \Illuminate\Support\Collection<int, LoanSchedule> $schedules
     * @param string[] $order bucket names in the order to apply — must be a permutation
     *   of ['penalty','fee','interest','principal']; principal is always evaluated last
     *   regardless of its position, since "how much is left after everything else" is
     *   principal's defining property, not an independent priority choice.
     * @return array{0: array<int, array<string, mixed>>, 1: string}
     */
    private function allocate($schedules, string $amount, array $order): array
    {
        $remaining   = $amount;
        $allocations = [];

        // Column each bucket owed-amount is read from, per installment.
        $owedColumns = [
            'penalty'   => ['due' => 'penalty_due', 'paid' => 'penalty_paid'],
            'fee'       => ['due' => 'fee_due', 'paid' => 'fee_paid'],
            'interest'  => ['due' => 'interest_due', 'paid' => 'interest_paid'],
            'principal' => ['due' => 'principal_due', 'paid' => 'principal_paid'],
        ];

        foreach ($schedules as $schedule) {
            if (bccomp($remaining, '0', 6) <= 0) {
                break;
            }

            $owed  = [];
            $alloc = [];

            foreach ($order as $bucket) {
                if (! isset($owedColumns[$bucket])) {
                    continue;
                }

                ['due' => $dueColumn, 'paid' => $paidColumn] = $owedColumns[$bucket];
                $owed[$bucket]  = bcsub((string) $schedule->{$dueColumn}, (string) $schedule->{$paidColumn}, 6);
                $alloc[$bucket] = $this->take($remaining, $owed[$bucket]);
                $remaining      = bcsub($remaining, $alloc[$bucket], 6);
            }

            $hasAllocation = collect($alloc)->contains(fn ($value) => bccomp($value, '0', 6) > 0);

            if ($hasAllocation) {
                $allocations[] = [
                    'schedule'   => $schedule,
                    'penalty'    => $this->round2($alloc['penalty'] ?? '0'),
                    'fee'        => $this->round2($alloc['fee'] ?? '0'),
                    'interest'   => $this->round2($alloc['interest'] ?? '0'),
                    'principal'  => $this->round2($alloc['principal'] ?? '0'),
                ];
            }

            // A partially-covered non-principal bucket stops the walk here — never skip
            // ahead into a later installment while an earlier one still has an unpaid
            // penalty/fee/interest component. Principal is exempt from this check: it's
            // fine (expected, even) for an installment's principal to go only partially
            // paid while later installments' penalty/fee/interest remain untouched — that
            // shortfall is exactly what should surface as arrears/partial status, not
            // block the walk.
            $blockedBucket = collect($owed)
                ->except(['principal'])
                ->first(fn ($owedAmount, $bucket) => bccomp($owedAmount, $alloc[$bucket] ?? '0', 6) > 0);

            if ($blockedBucket !== null) {
                break;
            }
        }

        return [$allocations, $this->round2($remaining)];
    }

    private function take(string $available, string $owed): string
    {
        if (bccomp($owed, '0', 6) <= 0) {
            return '0';
        }

        return bccomp($available, $owed, 6) >= 0 ? $owed : $available;
    }

    private function round2(string $value): string
    {
        return bcadd($value, '0', 2);
    }

    private function postLeg(Loan $loan, LoanProduct $product, Account $fundingAccount, string $amount, string $glRole, string $transactionType, ?LoanSchedule $schedule, ?string $description): LoanTransaction
    {
        $mapping = $product->generalLedgerMappings->firstWhere(
            'financial_account_type',
            LoanProductType::fromName($glRole)->value,
        );

        if (! $mapping) {
            throw ValidationException::withMessages([
                'loan_product_id' => ["This loan product has no {$glRole} GL mapping configured."],
            ]);
        }

        $financialTransaction = $this->postings->createAndApprove([
            'source_type'      => 'account',
            'source_id'        => $fundingAccount->id,
            'destination_type' => 'general_ledger',
            'destination_id'   => $mapping->general_ledger_id,
            'amount'           => $amount,
            'module'           => 'loans',
            'transaction_type' => $transactionType,
            'remarks'          => $description ?? "Repayment on loan '{$loan->account_number}'.",
        ]);

        return $this->transactions->record(
            loan: $loan,
            type: $transactionType,
            amount: $amount,
            posting: $financialTransaction->posting,
            schedule: $schedule,
            description: $description,
        );
    }
}
