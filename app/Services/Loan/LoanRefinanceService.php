<?php

declare(strict_types=1);

namespace App\Services\Loan;

use App\Enums\Product\LoanProductType;
use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRefinance;
use App\Services\Audit\AuditService;
use App\Services\Notification\NotificationService;
use App\Services\Posting\PostingService;
use App\Support\ReferenceGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Refinance = close out an existing loan by settling it from a brand-new loan, optionally
// with extra cash out to the customer on top of the old balance. Deliberately reuses
// LoanService::open()/approve() to disburse the new loan (money lands in the customer's
// account exactly as any fresh loan would).
//
// Settlement of the OLD loan deliberately does NOT go through
// LoanRepaymentService::record() — that method's waterfall allocates against every
// pending/partial schedule row's interest_due, including installments whose interest
// hasn't accrued yet, which can pull outstanding_interest negative on a full-balance
// payoff. A refinance payoff instead posts directly against the loan's own tracked
// outstanding_principal/outstanding_interest summary (same LOAN_PORTFOLIO/INTEREST_ON_LOANS
// GL roles a normal repayment uses) and explicitly closes out the old schedule.
class LoanRefinanceService
{
    public function __construct(
        private LoanService $loans,
        private PostingService $postings,
        private LoanTransactionService $transactions,
        private AuditService $audit,
        private NotificationService $notifications,
    ) {}

    public function list(int $perPage = 15, ?string $status = null)
    {
        return LoanRefinance::query()
            ->with(['oldLoan', 'newLoan', 'requestedBy', 'approvedBy'])
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): LoanRefinance
    {
        return LoanRefinance::with(['oldLoan', 'newLoan', 'requestedBy', 'approvedBy', 'rejectedBy'])->findOrFail($id);
    }

    public function request(Loan $loan, array $data): LoanRefinance
    {
        if ($loan->status !== 'active') {
            throw ValidationException::withMessages(['status' => ['Only an active loan can be refinanced.']]);
        }

        if ($loan->refinances()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['status' => ['A refinance request is already pending for this loan.']]);
        }

        $product = LoanProduct::findOrFail($data['loan_product_id']);
        $oldOutstanding    = bcadd((string) $loan->outstanding_principal, (string) $loan->outstanding_interest, 2);
        $additionalCashOut = (string) ($data['additional_cash_out'] ?? '0');
        $newPrincipal      = bcadd($oldOutstanding, $additionalCashOut, 2);

        $actor = Auth::guard('user')->user();

        $refinance = LoanRefinance::create([
            'old_loan_id'                  => $loan->id,
            'reference'                    => ReferenceGenerator::generate('LNF'),
            'loan_product_id'                => $product->id,
            'old_outstanding_amount'        => $oldOutstanding,
            'additional_cash_out'            => $additionalCashOut,
            'new_principal_amount'           => $newPrincipal,
            'new_duration'        => $data['new_duration'] ?? $loan->duration,
            'new_interest_rate'               => $data['new_interest_rate'] ?? $product->interest_rate,
            'reason'                         => $data['reason'] ?? null,
            'status'                         => 'pending',
            'requested_by'                    => $actor?->id,
            'disbursement_account_id'         => $data['disbursement_account_id'] ?? $loan->disbursement_account_id,
        ]);

        $this->audit->log(
            action: 'created',
            module: 'loan_refinances',
            auditable: $refinance,
            after: $refinance->toArray(),
            description: "Refinance requested for loan '{$loan->account_number}' by '{$actor?->username}'.",
        );

        $this->notifications->notifyPermission(
            'approve_loans',
            'loan_refinance',
            $refinance->id,
            'New loan refinance request',
            "Refinance of loan '{$loan->account_number}' is awaiting approval.",
        );

        return $refinance;
    }

    public function approve(LoanRefinance $refinance): LoanRefinance
    {
        if ($refinance->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Only a pending refinance can be approved.']]);
        }

        $actor = Auth::guard('user')->user();

        if ($actor?->hasRole('cashier')) {
            throw ValidationException::withMessages(['status' => ['Cashiers cannot approve loan refinances.']]);
        }

        if ($actor && (int) $refinance->requested_by === (int) $actor->id) {
            throw ValidationException::withMessages(['status' => ['You cannot approve a refinance you requested yourself.']]);
        }

        return DB::transaction(function () use ($refinance, $actor) {
            $oldLoan   = Loan::whereKey($refinance->old_loan_id)->lockForUpdate()->firstOrFail();
            $refinance = LoanRefinance::whereKey($refinance->id)->lockForUpdate()->firstOrFail();

            if ($refinance->status !== 'pending') {
                throw ValidationException::withMessages(['status' => ['Only a pending refinance can be approved.']]);
            }

            if ($oldLoan->status !== 'active') {
                throw ValidationException::withMessages(['status' => ['The loan being refinanced is no longer active.']]);
            }

            // Re-derive against the CURRENT outstanding balance — it may have drifted
            // (a repayment landed) between request and approval.
            $oldOutstanding = bcadd((string) $oldLoan->outstanding_principal, (string) $oldLoan->outstanding_interest, 2);
            $newPrincipal   = bcadd($oldOutstanding, (string) $refinance->additional_cash_out, 2);

            $newLoan = $this->loans->open([
                'loan_product_id'          => $refinance->loan_product_id,
                'disbursement_account_id'    => $refinance->disbursement_account_id,
                'principal_amount'           => $newPrincipal,
                'duration'       => $refinance->new_duration,
            ]);

            if ($refinance->new_interest_rate !== null) {
                $newLoan->update(['interest_rate' => $refinance->new_interest_rate]);
            }
            $newLoan->refresh();

            $newLoan = $this->loans->approve($newLoan, skipSelfApprovalCheck: true);

            $this->settleOldLoan($oldLoan, $newLoan, $refinance);

            $oldLoan->refresh();
            $oldLoan->schedules()
                ->whereIn('status', ['pending', 'partial'])
                ->update(['superseded_at' => now(), 'status' => 'superseded']);
            $oldLoan->update(['status' => 'closed']);
            $oldLoan->refresh();

            $refinance->update([
                'status'                => 'approved',
                'approved_by'            => $actor?->id,
                'approved_at'            => now(),
                'new_loan_id'             => $newLoan->id,
                'old_outstanding_amount'  => $oldOutstanding,
                'new_principal_amount'    => $newPrincipal,
            ]);

            $this->audit->log(
                action: 'approved',
                module: 'loan_refinances',
                auditable: $refinance,
                after: ['status' => 'approved', 'new_loan_id' => $newLoan->id],
                description: "Refinance of loan '{$oldLoan->account_number}' into '{$newLoan->account_number}' was approved by '{$actor?->username}'.",
            );

            $customer = $oldLoan->customer;
            $this->notifications->notifyCustomerEmail(
                actor: $actor,
                customer: $customer,
                type: 'loan_refinance_approved',
                subject: 'Your Loan Has Been Refinanced',
                body: "Dear " . trim("{$customer?->first_name} {$customer?->last_name}") . ",<br><br>"
                    . "Your loan on account <strong>{$oldLoan->account_number}</strong> has been refinanced into a new loan.<br><br>"
                    . "New Loan Account: <strong>{$newLoan->account_number}</strong><br>"
                    . "New Principal: <strong>" . number_format((float) $newPrincipal, 2) . "</strong><br><br>"
                    . "Your previous loan is now closed. Thank you for banking with us.",
            );

            return $refinance->fresh();
        });
    }

    private function settleOldLoan(Loan $oldLoan, Loan $newLoan, LoanRefinance $refinance): void
    {
        $principal = (string) $oldLoan->outstanding_principal;
        $interest  = (string) $oldLoan->outstanding_interest;

        if (bccomp($principal, '0', 2) <= 0 && bccomp($interest, '0', 2) <= 0) {
            return;
        }

        $product = $oldLoan->loanProduct()->with('generalLedgerMappings.generalLedger')->first();
        $mappings = $product->generalLedgerMappings->keyBy('financial_account_type');

        $interestGl = $oldLoan->interest_suspended
            ? $mappings[LoanProductType::SUSPENDED_INTEREST->value]?->generalLedger
            : $mappings[LoanProductType::INTEREST_ON_LOANS->value]?->generalLedger;

        $fundingAccount = Account::findOrFail($refinance->disbursement_account_id);

        if (bccomp($principal, '0', 2) > 0) {
            // Debit the funding account, credit LOAN_PORTFOLIO (asset shrinks — the old
            // loan's principal is being paid off).
            $financialTransaction = $this->transactions->moveLoanPortfolio(
                loan: $oldLoan,
                account: $fundingAccount,
                amount: $principal,
                accountIsCredited: false,
                transactionType: 'repayment_principal',
                description: "Payoff of loan '{$oldLoan->account_number}' via refinance '{$refinance->reference}'.",
            );

            $this->transactions->record(
                loan: $oldLoan,
                type: 'repayment_principal',
                amount: $principal,
                description: "Principal payoff via refinance '{$refinance->reference}' into loan '{$newLoan->account_number}'.",
                transactionReference: $financialTransaction->transaction_reference,
            );

            $oldLoan->refresh();
        }

        if (bccomp($interest, '0', 2) > 0 && $interestGl) {
            $financialTransaction = $this->postings->createAndApprove([
                'source_type'      => 'account',
                'source_id'        => $fundingAccount->id,
                'destination_type' => 'general_ledger',
                'destination_id'   => $interestGl->id,
                'amount'           => $interest,
                'module'           => 'loans',
                'transaction_type' => 'repayment_interest',
                'remarks'          => "Payoff of loan '{$oldLoan->account_number}' via refinance '{$refinance->reference}'.",
            ]);

            $this->transactions->record(
                loan: $oldLoan,
                type: 'repayment_interest',
                amount: $interest,
                posting: $financialTransaction->posting,
                description: "Interest payoff via refinance '{$refinance->reference}' into loan '{$newLoan->account_number}'.",
            );
        }
    }

    public function reject(LoanRefinance $refinance, string $reason): LoanRefinance
    {
        if ($refinance->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Only a pending refinance can be rejected.']]);
        }

        $actor = Auth::guard('user')->user();

        $refinance->update([
            'status'             => 'rejected',
            'rejected_by'         => $actor?->id,
            'rejected_at'         => now(),
            'rejection_reason'    => $reason,
        ]);

        $this->audit->log(
            action: 'rejected',
            module: 'loan_refinances',
            auditable: $refinance,
            after: ['status' => 'rejected', 'reason' => $reason],
            description: "Refinance request for loan '{$refinance->oldLoan?->account_number}' was rejected by '{$actor?->username}': {$reason}",
        );

        $customer = $refinance->oldLoan?->customer;
        $this->notifications->notifyCustomerEmail(
            actor: $actor,
            customer: $customer,
            type: 'loan_refinance_rejected',
            subject: 'Your Loan Refinance Request Was Declined',
            body: "Dear " . trim("{$customer?->first_name} {$customer?->last_name}") . ",<br><br>"
                . "Your refinance request on loan account <strong>{$refinance->oldLoan?->account_number}</strong> was declined.<br><br>"
                . "Reason: <strong>{$reason}</strong><br><br>"
                . "Please contact us if you have any questions.",
        );

        return $refinance;
    }
}
