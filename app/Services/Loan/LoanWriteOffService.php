<?php

declare(strict_types=1);

namespace App\Services\Loan;

use App\Enums\Product\LoanProductType;
use App\Models\Loan;
use App\Models\LoanWriteOff;
use App\Services\Audit\AuditService;
use App\Services\Notification\NotificationService;
use App\Services\Posting\PostingService;
use App\Support\ReferenceGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Mirrors InvestmentPartialLiquidationService's exact request()/approve()/reject()
// shape: separate table (real history of every attempt, not columns-on-Loan), blocks
// cashiers and self-approval, re-locks and re-validates at approval time since balances
// can drift between request and approval.
//
// The write-off posting is a genuine GL-to-GL entry with no customer party at all —
// unlike disbursement/repayment, LOAN_PORTFOLIO is a real leg here, not a mirror (see
// LoanTransactionService's PORTFOLIO_MIRROR_DIRECTION comment).
class LoanWriteOffService
{
    public function __construct(
        private PostingService $postings,
        private LoanTransactionService $transactions,
        private AuditService $audit,
        private NotificationService $notifications,
    ) {}

    public function list(int $perPage = 15, ?string $status = null)
    {
        return LoanWriteOff::query()
            ->with(['loan', 'requestedBy', 'approvedBy'])
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): LoanWriteOff
    {
        return LoanWriteOff::with(['loan', 'requestedBy', 'approvedBy', 'rejectedBy'])->findOrFail($id);
    }

    public function listForLoan(Loan $loan)
    {
        return $loan->writeOffs()->with(['requestedBy', 'approvedBy', 'rejectedBy'])->latest()->get();
    }

    public function request(Loan $loan, string $reason): LoanWriteOff
    {
        if ($loan->status !== 'active') {
            throw ValidationException::withMessages(['status' => ['Only an active loan can be written off.']]);
        }

        if (bccomp((string) $loan->outstanding_principal, '0', 2) <= 0) {
            throw ValidationException::withMessages(['status' => ['This loan has no outstanding principal to write off.']]);
        }

        if ($loan->writeOffs()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['status' => ['A write-off request is already pending for this loan.']]);
        }

        $actor = Auth::guard('user')->user();

        $writeOff = $loan->writeOffs()->create([
            'reference'         => ReferenceGenerator::generate('LNW'),
            'principal_amount'  => $loan->outstanding_principal,
            'interest_amount'   => $loan->outstanding_interest,
            'reason'            => $reason,
            'status'            => 'pending',
            'requested_by'      => $actor?->id,
        ]);

        $this->audit->log(
            action: 'created',
            module: 'loan_write_offs',
            auditable: $writeOff,
            after: $writeOff->toArray(),
            description: "Write-off requested for loan '{$loan->account_number}' by '{$actor?->username}': {$reason}",
        );

        $this->notifications->notifyPermission(
            'write_off_loans',
            'loan_write_off',
            $writeOff->id,
            'New loan write-off request',
            "Write-off of loan '{$loan->account_number}' is awaiting approval.",
        );

        return $writeOff;
    }

    public function approve(LoanWriteOff $writeOff): LoanWriteOff
    {
        if ($writeOff->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Only a pending write-off can be approved.']]);
        }

        $actor = Auth::guard('user')->user();

        if ($actor?->hasRole('cashier')) {
            throw ValidationException::withMessages(['status' => ['Cashiers cannot approve loan write-offs.']]);
        }

        if ($actor && (int) $writeOff->requested_by === (int) $actor->id) {
            throw ValidationException::withMessages(['status' => ['You cannot approve a write-off you requested yourself.']]);
        }

        return DB::transaction(function () use ($writeOff, $actor) {
            $loan     = Loan::whereKey($writeOff->loan_id)->lockForUpdate()->firstOrFail();
            $writeOff = LoanWriteOff::whereKey($writeOff->id)->lockForUpdate()->firstOrFail();

            if ($writeOff->status !== 'pending') {
                throw ValidationException::withMessages(['status' => ['Only a pending write-off can be approved.']]);
            }

            // Re-validate under lock — the outstanding balance may have changed (a
            // repayment landed) between request and approval.
            $principal = (string) $loan->outstanding_principal;
            $interest  = (string) $loan->outstanding_interest;

            if (bccomp($principal, '0', 2) <= 0) {
                throw ValidationException::withMessages(['status' => ['This loan no longer has an outstanding principal to write off — it may have been repaid since the request.']]);
            }

            $product = $loan->loanProduct()->with('generalLedgerMappings.generalLedger')->first();
            $mappings = $product->generalLedgerMappings->keyBy('financial_account_type');

            $expenseGl   = $mappings[LoanProductType::LOSSES_WRITTEN_OFF->value]?->generalLedger;
            $portfolioGl = $mappings[LoanProductType::LOAN_PORTFOLIO->value]?->generalLedger;
            // Whichever GL currently holds the uncollected interest asset — suspended if
            // the loan is non-performing, otherwise the plain receivable role.
            $interestAssetGl = $loan->interest_suspended
                ? $mappings[LoanProductType::SUSPENDED_INTEREST->value]?->generalLedger
                : $mappings[LoanProductType::INTEREST_RECEIVABLE->value]?->generalLedger;

            if (! $expenseGl || ! $portfolioGl) {
                throw ValidationException::withMessages(['loan_product_id' => ['This loan product is missing its Losses Written Off or Loan Portfolio GL mapping.']]);
            }

            $totalWriteOff = bcadd($principal, $interest, 2);
            $mainPosting   = null;

            $postLeg = function (int $sourceGlId, int $destinationGlId, string $amount, string $remarks) use (&$mainPosting) {
                if (bccomp($amount, '0', 2) <= 0) {
                    return;
                }

                $financialTransaction = $this->postings->createAndApprove([
                    'source_type'      => 'general_ledger',
                    'source_id'        => $sourceGlId,
                    'destination_type' => 'general_ledger',
                    'destination_id'   => $destinationGlId,
                    'amount'           => $amount,
                    'module'           => 'loans',
                    'transaction_type' => 'write_off',
                    'remarks'          => $remarks,
                ]);

                $mainPosting ??= $financialTransaction->posting;
            };

            $postLeg($expenseGl->id, $portfolioGl->id, $principal, "Write-off of principal on loan '{$loan->account_number}'.");

            if ($interestAssetGl) {
                $postLeg($expenseGl->id, $interestAssetGl->id, $interest, "Write-off of interest on loan '{$loan->account_number}'.");
            }

            $transaction = $this->transactions->record(
                loan: $loan,
                type: 'write_off',
                amount: $totalWriteOff,
                posting: $mainPosting,
                description: "Write-off approved by '{$actor?->username}': {$writeOff->reason}",
            );

            $writeOff->update([
                'status'               => 'approved',
                'approved_by'           => $actor?->id,
                'approved_at'           => now(),
                'loan_transaction_id'    => $transaction->id,
            ]);

            $this->audit->log(
                action: 'approved',
                module: 'loan_write_offs',
                auditable: $writeOff,
                after: ['status' => 'approved', 'principal' => $principal, 'interest' => $interest],
                description: "Write-off of loan '{$loan->account_number}' was approved by '{$actor?->username}'.",
            );

            $customer = $loan->customer;
            $this->notifications->notifyCustomerEmail(
                actor: $actor,
                customer: $customer,
                type: 'loan_write_off',
                subject: 'Notice Regarding Your Loan Account',
                body: "Dear " . trim("{$customer?->first_name} {$customer?->last_name}") . ",<br><br>"
                    . "Your loan on account <strong>{$loan->account_number}</strong> has been written off due to non-payment.<br><br>"
                    . "Written-Off Principal: <strong>" . number_format((float) $principal, 2) . "</strong><br>"
                    . "Written-Off Interest: <strong>" . number_format((float) $interest, 2) . "</strong><br><br>"
                    . "This does not cancel the debt — please contact us to discuss repayment options.",
            );

            return $writeOff->fresh();
        });
    }

    public function reject(LoanWriteOff $writeOff, string $reason): LoanWriteOff
    {
        if ($writeOff->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Only a pending write-off can be rejected.']]);
        }

        $actor = Auth::guard('user')->user();

        $writeOff->update([
            'status'             => 'rejected',
            'rejected_by'         => $actor?->id,
            'rejected_at'         => now(),
            'rejection_reason'    => $reason,
        ]);

        $this->audit->log(
            action: 'rejected',
            module: 'loan_write_offs',
            auditable: $writeOff,
            after: ['status' => 'rejected', 'reason' => $reason],
            description: "Write-off request for loan '{$writeOff->loan?->account_number}' was rejected by '{$actor?->username}': {$reason}",
        );

        $customer = $writeOff->loan?->customer;
        $this->notifications->notifyCustomerEmail(
            actor: $actor,
            customer: $customer,
            type: 'loan_write_off_rejected',
            subject: 'Update on Your Loan Account',
            body: "Dear " . trim("{$customer?->first_name} {$customer?->last_name}") . ",<br><br>"
                . "A write-off request on your loan account <strong>{$writeOff->loan?->account_number}</strong> was declined.<br><br>"
                . "Your loan remains active. Please contact us if you have any questions.",
        );

        return $writeOff;
    }
}
