<?php

declare(strict_types=1);

namespace App\Services\Loan;

use App\Enums\Product\LoanProductType;
use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use App\Models\LoanTransaction;
use App\Services\Account\AccountPostingService;
use App\Services\Audit\AuditService;
use App\Services\Notification\NotificationService;
use App\Services\Posting\PostingService;
use App\Support\ReferenceGenerator;
use Illuminate\Support\Facades\Auth;
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
// Repayment is maker-checker like every other loan lifecycle action: request() stages a
// pending LoanRepayment (validated, but nothing posted yet), a different admin
// approve()s (TOTP) or reject()s it. Mirrors LoanWriteOffService's exact shape, with one
// deliberate difference — no cashier-role block and no "only one pending at a time"
// restriction, since request/approve here are gated purely on the repay_loans
// permission (anyone holding it can request; anyone holding it who isn't the requester
// can approve), and multiple repayments can legitimately queue up for approval.
// approve() re-locks and re-runs allocate() against the LIVE schedule state rather than
// trusting anything computed at request time, since the schedule can genuinely change
// between request and approval (another repayment approved in the meantime, a
// restructure, penalty accrual).
class LoanRepaymentService
{
    public function __construct(
        private PostingService $postings,
        private LoanTransactionService $transactions,
        private AccountPostingService $accountPostings,
        private AuditService $audit,
        private NotificationService $notifications,
    ) {}

    public function list(int $perPage = 15, ?string $status = null)
    {
        return LoanRepayment::query()
            ->with(['loan', 'fundingAccount', 'requestedBy', 'approvedBy', 'loanTransactions'])
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): LoanRepayment
    {
        return LoanRepayment::with(['loan', 'fundingAccount', 'requestedBy', 'approvedBy', 'rejectedBy', 'loanTransactions'])->findOrFail($id);
    }

    public function listForLoan(Loan $loan)
    {
        return $loan->repayments()->with(['fundingAccount', 'requestedBy', 'approvedBy', 'rejectedBy', 'loanTransactions'])->latest()->get();
    }

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

    public function request(Loan $loan, string $amount, int $fundingAccountId, ?string $description = null): LoanRepayment
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

        $fundingAccount = Account::findOrFail($fundingAccountId);

        // A repayment must come out of the borrowing customer's own money — never a
        // different customer's account, regardless of who's operating the till.
        if ((int) $fundingAccount->customer_id !== (int) $loan->customer_id) {
            throw ValidationException::withMessages([
                'funding_account_id' => ['The funding account must belong to the loan\'s own customer.'],
            ]);
        }

        if ((int) $fundingAccount->currency_id !== (int) $loan->currency_id) {
            throw ValidationException::withMessages([
                'funding_account_id' => ['The funding account currency does not match the loan currency.'],
            ]);
        }

        if (! $this->accountPostings->hasSufficientFunds($fundingAccount, $amount)) {
            throw ValidationException::withMessages([
                'funding_account_id' => ["Account '{$fundingAccount->account_number}' has insufficient funds for this repayment."],
            ]);
        }

        $actor = Auth::guard('user')->user();

        $repayment = $loan->repayments()->create([
            'reference'           => ReferenceGenerator::generate('LNR'),
            'amount'              => $amount,
            'funding_account_id'  => $fundingAccount->id,
            'description'         => $description,
            'status'              => 'pending',
            'requested_by'        => $actor?->id,
        ]);

        $this->audit->log(
            action: 'created',
            module: 'loan_repayments',
            auditable: $repayment,
            after: $repayment->toArray(),
            description: "Repayment of {$amount} requested for loan '{$loan->account_number}' by '{$actor?->username}'.",
        );

        $this->notifications->notifyPermission(
            'repay_loans',
            'loan_repayment',
            $repayment->id,
            'New loan repayment request',
            "A repayment on loan '{$loan->account_number}' is awaiting approval.",
        );

        return $repayment;
    }

    public function approve(LoanRepayment $repayment): LoanRepayment
    {
        if ($repayment->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Only a pending repayment can be approved.']]);
        }

        $actor = Auth::guard('user')->user();

        if ($actor && (int) $repayment->requested_by === (int) $actor->id) {
            throw ValidationException::withMessages(['status' => ['You cannot approve a repayment you requested yourself.']]);
        }

        return DB::transaction(function () use ($repayment, $actor) {
            $loan      = Loan::whereKey($repayment->loan_id)->lockForUpdate()->firstOrFail();
            $repayment = LoanRepayment::whereKey($repayment->id)->lockForUpdate()->firstOrFail();

            if ($repayment->status !== 'pending') {
                throw ValidationException::withMessages(['status' => ['Only a pending repayment can be approved.']]);
            }

            if ($loan->status !== 'active') {
                throw ValidationException::withMessages(['status' => ['Only active loans can receive repayments.']]);
            }

            $amount = (string) $repayment->amount;

            $fundingAccount = Account::findOrFail($repayment->funding_account_id);

            // Re-validate under lock — nothing here is trusted from request time, since
            // the account/loan state may have drifted since then (another repayment
            // landed, the account was reassigned, the balance moved).
            if ((int) $fundingAccount->customer_id !== (int) $loan->customer_id) {
                throw ValidationException::withMessages([
                    'funding_account_id' => ['The funding account must belong to the loan\'s own customer.'],
                ]);
            }

            if ((int) $fundingAccount->currency_id !== (int) $loan->currency_id) {
                throw ValidationException::withMessages([
                    'funding_account_id' => ['The funding account currency does not match the loan currency.'],
                ]);
            }

            if (! $this->accountPostings->hasSufficientFunds($fundingAccount, $amount)) {
                throw ValidationException::withMessages([
                    'funding_account_id' => ["Account '{$fundingAccount->account_number}' has insufficient funds for this repayment."],
                ]);
            }

            // Allocation is computed fresh here, against the schedule as it stands right
            // now — never trusted from request time.
            $schedules = $loan->schedules()
                ->whereIn('status', ['pending', 'partial'])
                ->orderBy('installment_number')
                ->get();

            $order = $loan->payment_allocation_order ?: Loan::DEFAULT_PAYMENT_ALLOCATION_ORDER;

            [$allocations, $remaining] = $this->allocate($schedules, $amount, $order);

            $product = $loan->loanProduct()->with('generalLedgerMappings.generalLedger')->first();

            // Bucket -> [GL role, transaction type] — posted in the loan's configured
            // order so the transaction log reads in the order money was actually applied.
            // 'principal' is handled separately below (it moves LOAN_PORTFOLIO directly,
            // not a product GL mapping via postLeg()).
            $bucketConfig = [
                'penalty'   => ['INCOME_FROM_PENALTIES', 'repayment_penalty'],
                'fee'       => ['INCOME_FROM_FEES', 'repayment_fee'],
                'interest'  => ['INTEREST_ON_LOANS', 'repayment_interest'],
            ];

            foreach ($allocations as $allocation) {
                foreach ($order as $bucket) {
                    if (bccomp($allocation[$bucket], '0', 2) <= 0) {
                        continue;
                    }

                    if ($bucket === 'principal') {
                        // Debit the customer's funding account, credit LOAN_PORTFOLIO
                        // (asset shrinks — less principal outstanding).
                        $financialTransaction = $this->transactions->moveLoanPortfolio(
                            loan: $loan,
                            account: $fundingAccount,
                            amount: $allocation['principal'],
                            accountIsCredited: false,
                            transactionType: 'repayment_principal',
                            description: $repayment->description ?? "Repayment on loan '{$loan->account_number}'.",
                        );

                        $this->transactions->record(
                            loan: $loan,
                            type: 'repayment_principal',
                            amount: $allocation['principal'],
                            schedule: $allocation['schedule'],
                            description: $repayment->description,
                            loanRepayment: $repayment,
                            transactionReference: $financialTransaction->transaction_reference,
                        );

                        continue;
                    }

                    if (! isset($bucketConfig[$bucket])) {
                        continue;
                    }

                    [$glRole, $transactionType] = $bucketConfig[$bucket];
                    $this->postLeg($loan, $product, $fundingAccount, $allocation[$bucket], $glRole, $transactionType, $allocation['schedule'], $repayment->description, $repayment);

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
                $this->postLeg($loan, $product, $fundingAccount, $remaining, 'OVERPAYMENT', 'overpayment', null, $repayment->description, $repayment);
            }

            $repayment->update([
                'status'       => 'approved',
                'approved_by'  => $actor?->id,
                'approved_at'  => now(),
            ]);

            $this->audit->log(
                action: 'approved',
                module: 'loan_repayments',
                auditable: $repayment,
                after: ['status' => 'approved', 'amount' => $amount],
                description: "Repayment of {$amount} on loan '{$loan->account_number}' was approved by '{$actor?->username}'.",
            );

            $loan->refresh();
            $customer = $loan->customer;
            $this->notifications->notifyCustomerEmail(
                actor: $actor,
                customer: $customer,
                type: 'loan_repayment_approved',
                subject: 'Your Loan Repayment Has Been Applied',
                body: "Dear " . trim("{$customer?->first_name} {$customer?->last_name}") . ",<br><br>"
                    . "A repayment of <strong>" . number_format((float) $amount, 2) . "</strong> has been applied to your loan account <strong>{$loan->account_number}</strong>.<br><br>"
                    . "Outstanding Principal: <strong>" . number_format((float) $loan->outstanding_principal, 2) . "</strong><br>"
                    . "Outstanding Interest: <strong>" . number_format((float) $loan->outstanding_interest, 2) . "</strong><br><br>"
                    . "Thank you for banking with us.",
            );

            return $repayment->fresh();
        });
    }

    public function reject(LoanRepayment $repayment, string $reason): LoanRepayment
    {
        if ($repayment->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Only a pending repayment can be rejected.']]);
        }

        $actor = Auth::guard('user')->user();

        $repayment->update([
            'status'            => 'rejected',
            'rejected_by'        => $actor?->id,
            'rejected_at'        => now(),
            'rejection_reason'   => $reason,
        ]);

        $this->audit->log(
            action: 'rejected',
            module: 'loan_repayments',
            auditable: $repayment,
            after: ['status' => 'rejected', 'reason' => $reason],
            description: "Repayment request for loan '{$repayment->loan?->account_number}' was rejected by '{$actor?->username}': {$reason}",
        );

        $customer = $repayment->loan?->customer;
        $this->notifications->notifyCustomerEmail(
            actor: $actor,
            customer: $customer,
            type: 'loan_repayment_rejected',
            subject: 'Your Loan Repayment Was Not Applied',
            body: "Dear " . trim("{$customer?->first_name} {$customer?->last_name}") . ",<br><br>"
                . "A repayment request on your loan account <strong>{$repayment->loan?->account_number}</strong> was declined.<br><br>"
                . "Reason: <strong>{$reason}</strong><br><br>"
                . "Please contact us if you have any questions.",
        );

        return $repayment;
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

    private function postLeg(Loan $loan, LoanProduct $product, Account $fundingAccount, string $amount, string $glRole, string $transactionType, ?LoanSchedule $schedule, ?string $description, LoanRepayment $loanRepayment): LoanTransaction
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
            loanRepayment: $loanRepayment,
        );
    }
}
