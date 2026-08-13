<?php

declare(strict_types=1);

namespace App\Services\Loan;

use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanTopup;
use App\Services\Audit\AuditService;
use App\Services\Notification\NotificationService;
use App\Support\ReferenceGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Top-up = additional disbursement on an existing active loan. Real money moves (same
// LOAN_PORTFOLIO -> customer account leg as the original disbursement), new outstanding
// principal = old outstanding + top-up amount, schedule regenerated from there.
class LoanTopupService
{
    public function __construct(
        private LoanScheduleService $schedules,
        private LoanTransactionService $transactions,
        private AuditService $audit,
        private NotificationService $notifications,
    ) {}

    public function list(int $perPage = 15, ?string $status = null)
    {
        return LoanTopup::query()
            ->with(['loan', 'requestedBy', 'approvedBy'])
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): LoanTopup
    {
        return LoanTopup::with(['loan', 'requestedBy', 'approvedBy', 'rejectedBy'])->findOrFail($id);
    }

    // Read-only — previews the schedule against outstanding_principal + topup_amount
    // before anyone commits to requesting it. No money moves, nothing persisted.
    public function preview(Loan $loan, array $data): array
    {
        if ($loan->status !== 'active') {
            throw ValidationException::withMessages(['status' => ['Only an active loan can be topped up.']]);
        }

        $topupAmount = (string) ($data['topup_amount'] ?? '0');

        if (bccomp($topupAmount, '0', 2) <= 0) {
            throw ValidationException::withMessages(['topup_amount' => ['Top-up amount must be greater than zero.']]);
        }

        $currentSchedule = $loan->schedules()
            ->where('version', (int) ($loan->schedules()->max('version') ?: 1))
            ->whereIn('status', ['pending', 'partial'])
            ->orderBy('installment_number')
            ->get();

        $projectedPrincipal = bcadd((string) $loan->outstanding_principal, $topupAmount, 2);

        $projected = $this->schedules->previewRemaining(
            $loan,
            isset($data['new_duration']) ? (int) $data['new_duration'] : null,
            null,
            isset($data['new_interest_rate']) ? (string) $data['new_interest_rate'] : null,
            $projectedPrincipal,
        );

        $currentTotalInterest = $currentSchedule->reduce(fn ($carry, $row) => bcadd($carry, (string) $row->interest_due, 2), '0.00');

        return [
            'current_schedule'                 => $currentSchedule,
            'current_total_interest'            => $currentTotalInterest,
            'current_outstanding_principal'      => (string) $loan->outstanding_principal,
            'projected_schedule'                => $projected['installments'],
            'projected_total_interest'           => $projected['total_interest'],
            'projected_outstanding_principal'    => $projectedPrincipal,
        ];
    }

    public function request(Loan $loan, array $data): LoanTopup
    {
        if ($loan->status !== 'active') {
            throw ValidationException::withMessages(['status' => ['Only an active loan can be topped up.']]);
        }

        if ($loan->topups()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['status' => ['A top-up request is already pending for this loan.']]);
        }

        if (bccomp((string) $data['topup_amount'], '0', 2) <= 0) {
            throw ValidationException::withMessages(['topup_amount' => ['Top-up amount must be greater than zero.']]);
        }

        $actor = Auth::guard('user')->user();

        $topup = $loan->topups()->create([
            'reference'                  => ReferenceGenerator::generate('LNT'),
            'topup_amount'               => $data['topup_amount'],
            'new_duration'    => $data['new_duration'] ?? null,
            'new_interest_rate'           => $data['new_interest_rate'] ?? null,
            'reason'                     => $data['reason'] ?? null,
            'disbursement_account_id'     => $data['disbursement_account_id'] ?? $loan->disbursement_account_id,
            'status'                     => 'pending',
            'requested_by'                => $actor?->id,
        ]);

        $this->audit->log(
            action: 'created',
            module: 'loan_topups',
            auditable: $topup,
            after: $topup->toArray(),
            description: "Top-up requested for loan '{$loan->account_number}' by '{$actor?->username}'.",
        );

        $this->notifications->notifyPermission(
            'approve_loans',
            'loan_topup',
            $topup->id,
            'New loan top-up request',
            "Top-up of loan '{$loan->account_number}' is awaiting approval.",
        );

        return $topup;
    }

    public function approve(LoanTopup $topup): LoanTopup
    {
        if ($topup->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Only a pending top-up can be approved.']]);
        }

        $actor = Auth::guard('user')->user();

        if ($actor?->hasRole('cashier')) {
            throw ValidationException::withMessages(['status' => ['Cashiers cannot approve loan top-ups.']]);
        }

        if ($actor && (int) $topup->requested_by === (int) $actor->id) {
            throw ValidationException::withMessages(['status' => ['You cannot approve a top-up you requested yourself.']]);
        }

        return DB::transaction(function () use ($topup, $actor) {
            $loan  = Loan::whereKey($topup->loan_id)->lockForUpdate()->firstOrFail();
            $topup = LoanTopup::whereKey($topup->id)->lockForUpdate()->firstOrFail();

            if ($topup->status !== 'pending') {
                throw ValidationException::withMessages(['status' => ['Only a pending top-up can be approved.']]);
            }

            if ($loan->status !== 'active') {
                throw ValidationException::withMessages(['status' => ['Loan is no longer active.']]);
            }

            $principalBefore = (string) $loan->outstanding_principal;
            $amount          = (string) $topup->topup_amount;

            $disbursementAccount = Account::with('accountProduct')->findOrFail($topup->disbursement_account_id);

            if (! in_array($disbursementAccount->accountProduct?->product_type, ['savings', 'current'], true)) {
                throw ValidationException::withMessages([
                    'disbursement_account_id' => ['A loan top-up can only be disbursed into a Savings or Current account.'],
                ]);
            }

            // Debit LOAN_PORTFOLIO (asset grows — more principal outstanding), credit
            // the customer's disbursement account.
            $financialTransaction = $this->transactions->moveLoanPortfolio(
                loan: $loan,
                account: $disbursementAccount,
                amount: $amount,
                accountIsCredited: true,
                transactionType: 'loan_topup',
                description: "Top-up disbursement for loan '{$loan->account_number}'.",
            );

            $oldVersion = (int) ($loan->schedules()->max('version') ?: 1);

            $transaction = $this->transactions->record(
                loan: $loan,
                type: 'topup',
                amount: $amount,
                description: "Top-up approved by '{$actor?->username}'.",
                transactionReference: $financialTransaction->transaction_reference,
            );

            $loan->refresh();

            if ($topup->new_interest_rate !== null) {
                $loan->update(['interest_rate' => $topup->new_interest_rate]);
            }
            if ($topup->new_duration !== null) {
                $loan->update(['duration' => $topup->new_duration]);
            }
            $loan->refresh();

            $this->schedules->regenerateRemaining(
                $loan,
                $topup->new_duration,
                null,
                $topup->new_interest_rate !== null ? (string) $topup->new_interest_rate : null,
            );

            $newVersion = (int) ($loan->schedules()->max('version') ?: $oldVersion);

            $topup->update([
                'status'                => 'approved',
                'approved_by'            => $actor?->id,
                'approved_at'            => now(),
                'principal_before'        => $principalBefore,
                'principal_after'         => $loan->outstanding_principal,
                'loan_transaction_id'     => $transaction->id,
                'old_schedule_version'    => $oldVersion,
                'new_schedule_version'    => $newVersion,
            ]);

            $this->audit->log(
                action: 'approved',
                module: 'loan_topups',
                auditable: $topup,
                after: ['status' => 'approved', 'principal_after' => $loan->outstanding_principal],
                description: "Top-up of loan '{$loan->account_number}' was approved by '{$actor?->username}'.",
            );

            $customer = $loan->customer;
            $this->notifications->notifyCustomerEmail(
                actor: $actor,
                customer: $customer,
                type: 'loan_topup_approved',
                subject: 'Your Loan Top-Up Has Been Approved',
                body: "Dear " . trim("{$customer?->first_name} {$customer?->last_name}") . ",<br><br>"
                    . "A top-up of <strong>" . number_format((float) $amount, 2) . "</strong> has been approved and disbursed on your loan account <strong>{$loan->account_number}</strong>.<br><br>"
                    . "New Outstanding Principal: <strong>" . number_format((float) $loan->outstanding_principal, 2) . "</strong><br><br>"
                    . "A new repayment schedule has been generated for your loan. Thank you for banking with us.",
            );

            return $topup->fresh();
        });
    }

    public function reject(LoanTopup $topup, string $reason): LoanTopup
    {
        if ($topup->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Only a pending top-up can be rejected.']]);
        }

        $actor = Auth::guard('user')->user();

        $topup->update([
            'status'             => 'rejected',
            'rejected_by'         => $actor?->id,
            'rejected_at'         => now(),
            'rejection_reason'    => $reason,
        ]);

        $this->audit->log(
            action: 'rejected',
            module: 'loan_topups',
            auditable: $topup,
            after: ['status' => 'rejected', 'reason' => $reason],
            description: "Top-up request for loan '{$topup->loan?->account_number}' was rejected by '{$actor?->username}': {$reason}",
        );

        $customer = $topup->loan?->customer;
        $this->notifications->notifyCustomerEmail(
            actor: $actor,
            customer: $customer,
            type: 'loan_topup_rejected',
            subject: 'Your Loan Top-Up Request Was Declined',
            body: "Dear " . trim("{$customer?->first_name} {$customer?->last_name}") . ",<br><br>"
                . "Your top-up request on loan account <strong>{$topup->loan?->account_number}</strong> was declined.<br><br>"
                . "Reason: <strong>{$reason}</strong><br><br>"
                . "Please contact us if you have any questions.",
        );

        return $topup;
    }
}
