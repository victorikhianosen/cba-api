<?php

declare(strict_types=1);

namespace App\Services\Loan;

use App\Models\Loan;
use App\Models\LoanRestructure;
use App\Services\Audit\AuditService;
use App\Services\Notification\NotificationService;
use App\Support\ReferenceGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Restructure = the heavier sibling of Reschedule: in addition to changing tenor/rate,
// may capitalize outstanding interest into principal (a genuine increase in what's owed,
// via the existing principal_adjustment primitive) and/or waive a portion (via the
// existing principal_waiver primitive) before regenerating the schedule on the new
// outstanding balance and terms.
class LoanRestructureService
{
    public function __construct(
        private LoanScheduleService $schedules,
        private LoanTransactionService $transactions,
        private AuditService $audit,
        private NotificationService $notifications,
    ) {}

    public function list(int $perPage = 15, ?string $status = null)
    {
        return LoanRestructure::query()
            ->with(['loan', 'requestedBy', 'approvedBy'])
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): LoanRestructure
    {
        return LoanRestructure::with(['loan', 'requestedBy', 'approvedBy', 'rejectedBy'])->findOrFail($id);
    }

    public function request(Loan $loan, array $data): LoanRestructure
    {
        if ($loan->status !== 'active') {
            throw ValidationException::withMessages(['status' => ['Only an active loan can be restructured.']]);
        }

        if ($loan->restructures()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['status' => ['A restructure request is already pending for this loan.']]);
        }

        $capitalizedInterest = (string) ($data['capitalized_interest'] ?? '0');
        $waivedAmount        = (string) ($data['waived_amount'] ?? '0');

        if (bccomp($capitalizedInterest, (string) $loan->outstanding_interest, 2) > 0) {
            throw ValidationException::withMessages(['capitalized_interest' => ['Cannot capitalize more interest than is currently outstanding.']]);
        }

        if (bccomp($waivedAmount, bcadd((string) $loan->outstanding_principal, (string) $loan->outstanding_interest, 2), 2) > 0) {
            throw ValidationException::withMessages(['waived_amount' => ['Waived amount cannot exceed the total outstanding balance.']]);
        }

        $actor = Auth::guard('user')->user();

        $restructure = $loan->restructures()->create([
            'reference'                  => ReferenceGenerator::generate('LNX'),
            'reason'                     => $data['reason'],
            'new_duration'    => $data['new_duration'] ?? null,
            'new_interest_rate'           => $data['new_interest_rate'] ?? null,
            'capitalized_interest'        => $capitalizedInterest,
            'capitalized_fees'            => (string) ($data['capitalized_fees'] ?? '0'),
            'waived_amount'               => $waivedAmount,
            'restructure_fee'             => $data['restructure_fee'] ?? 0,
            'status'                      => 'pending',
            'requested_by'                => $actor?->id,
        ]);

        $this->audit->log(
            action: 'created',
            module: 'loan_restructures',
            auditable: $restructure,
            after: $restructure->toArray(),
            description: "Restructure requested for loan '{$loan->account_number}' by '{$actor?->username}'.",
        );

        $this->notifications->notifyPermission(
            'restructure_loans',
            'loan_restructure',
            $restructure->id,
            'New loan restructure request',
            "Restructure of loan '{$loan->account_number}' is awaiting approval.",
        );

        return $restructure;
    }

    // Read-only — simulates the same capitalize/waiver arithmetic approve() uses (locally,
    // no DB writes) to get the effective principal the projected schedule would actually
    // be built on, then previews the schedule against that.
    public function preview(Loan $loan, array $data): array
    {
        if ($loan->status !== 'active') {
            throw ValidationException::withMessages(['status' => ['Only an active loan can be restructured.']]);
        }

        $capitalizedInterest = (string) ($data['capitalized_interest'] ?? '0');
        $waivedAmount        = (string) ($data['waived_amount'] ?? '0');

        if (bccomp($capitalizedInterest, (string) $loan->outstanding_interest, 2) > 0) {
            throw ValidationException::withMessages(['capitalized_interest' => ['Cannot capitalize more interest than is currently outstanding.']]);
        }

        if (bccomp($waivedAmount, bcadd((string) $loan->outstanding_principal, (string) $loan->outstanding_interest, 2), 2) > 0) {
            throw ValidationException::withMessages(['waived_amount' => ['Waived amount cannot exceed the total outstanding balance.']]);
        }

        $currentSchedule = $loan->schedules()
            ->where('version', (int) ($loan->schedules()->max('version') ?: 1))
            ->whereIn('status', ['pending', 'partial'])
            ->orderBy('installment_number')
            ->get();

        // Same order as approve(): capitalize first (principal grows, interest shrinks),
        // then waiver (principal-first, then interest) — purely local, nothing persisted.
        $simulatedPrincipal = (string) $loan->outstanding_principal;
        $simulatedInterest  = (string) $loan->outstanding_interest;

        if (bccomp($capitalizedInterest, '0', 2) > 0) {
            $simulatedInterest  = bcsub($simulatedInterest, $capitalizedInterest, 2);
            $simulatedPrincipal = bcadd($simulatedPrincipal, $capitalizedInterest, 2);
        }

        if (bccomp($waivedAmount, '0', 2) > 0) {
            $principalWaiver = bccomp($waivedAmount, $simulatedPrincipal, 2) >= 0 ? $simulatedPrincipal : $waivedAmount;
            $interestWaiver  = bcsub($waivedAmount, $principalWaiver, 2);
            $simulatedPrincipal = bcsub($simulatedPrincipal, $principalWaiver, 2);
            $simulatedInterest  = bcsub($simulatedInterest, $interestWaiver, 2);
        }

        $projected = $this->schedules->previewRemaining(
            $loan,
            isset($data['new_duration']) ? (int) $data['new_duration'] : null,
            null,
            isset($data['new_interest_rate']) ? (string) $data['new_interest_rate'] : null,
            $simulatedPrincipal,
        );

        $currentTotalInterest = $currentSchedule->reduce(fn ($carry, $row) => bcadd($carry, (string) $row->interest_due, 2), '0.00');

        return [
            'current_schedule'                 => $currentSchedule,
            'current_total_interest'            => $currentTotalInterest,
            'current_outstanding_principal'      => (string) $loan->outstanding_principal,
            'current_outstanding_interest'       => (string) $loan->outstanding_interest,
            'projected_schedule'                => $projected['installments'],
            'projected_total_interest'           => $projected['total_interest'],
            'projected_outstanding_principal'    => $simulatedPrincipal,
            'projected_outstanding_interest'     => $simulatedInterest,
        ];
    }

    public function approve(LoanRestructure $restructure): LoanRestructure
    {
        if ($restructure->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Only a pending restructure can be approved.']]);
        }

        $actor = Auth::guard('user')->user();

        if ($actor?->hasRole('cashier')) {
            throw ValidationException::withMessages(['status' => ['Cashiers cannot approve loan restructures.']]);
        }

        if ($actor && (int) $restructure->requested_by === (int) $actor->id) {
            throw ValidationException::withMessages(['status' => ['You cannot approve a restructure you requested yourself.']]);
        }

        return DB::transaction(function () use ($restructure, $actor) {
            $loan        = Loan::whereKey($restructure->loan_id)->lockForUpdate()->firstOrFail();
            $restructure = LoanRestructure::whereKey($restructure->id)->lockForUpdate()->firstOrFail();

            if ($restructure->status !== 'pending') {
                throw ValidationException::withMessages(['status' => ['Only a pending restructure can be approved.']]);
            }

            if ($loan->status !== 'active') {
                throw ValidationException::withMessages(['status' => ['Loan is no longer active.']]);
            }

            $capitalizedInterest = (string) $restructure->capitalized_interest;
            $waivedAmount        = (string) $restructure->waived_amount;

            if (bccomp($capitalizedInterest, (string) $loan->outstanding_interest, 2) > 0) {
                throw ValidationException::withMessages(['capitalized_interest' => ['Outstanding interest has changed since the request — please re-request.']]);
            }

            $oldVersion = (int) ($loan->schedules()->max('version') ?: 1);

            // Capitalize: interest owed becomes principal owed instead — reduces
            // outstanding_interest, increases outstanding_principal by the same amount.
            if (bccomp($capitalizedInterest, '0', 2) > 0) {
                $this->transactions->record($loan, 'interest_waiver', $capitalizedInterest, description: "Capitalized into principal on restructure '{$restructure->reference}'.");
                $loan->refresh();
                $this->transactions->record($loan, 'principal_adjustment', $capitalizedInterest, description: "Principal increased by capitalized interest on restructure '{$restructure->reference}'.");
                $loan->refresh();
            }

            // Waiver: reduces what's owed outright — applied to principal first, then interest.
            if (bccomp($waivedAmount, '0', 2) > 0) {
                $principalWaiver = bccomp($waivedAmount, (string) $loan->outstanding_principal, 2) >= 0
                    ? (string) $loan->outstanding_principal
                    : $waivedAmount;
                $interestWaiver = bcsub($waivedAmount, $principalWaiver, 2);

                if (bccomp($principalWaiver, '0', 2) > 0) {
                    $this->transactions->record($loan, 'principal_waiver', $principalWaiver, description: "Waived on restructure '{$restructure->reference}'.");
                    $loan->refresh();
                }
                if (bccomp($interestWaiver, '0', 2) > 0) {
                    $this->transactions->record($loan, 'interest_waiver', $interestWaiver, description: "Waived on restructure '{$restructure->reference}'.");
                    $loan->refresh();
                }
            }

            if ($restructure->new_interest_rate !== null) {
                $loan->update(['interest_rate' => $restructure->new_interest_rate]);
            }
            if ($restructure->new_duration !== null) {
                $loan->update(['duration' => $restructure->new_duration]);
            }
            $loan->refresh();

            $this->schedules->regenerateRemaining(
                $loan,
                $restructure->new_duration,
                null,
                $restructure->new_interest_rate !== null ? (string) $restructure->new_interest_rate : null,
            );

            $newVersion = (int) ($loan->schedules()->max('version') ?: $oldVersion);

            $transaction = $this->transactions->record(
                loan: $loan,
                type: 'restructure',
                amount: '0.00',
                description: "Restructure approved by '{$actor?->username}': {$restructure->reason}",
            );

            $restructure->update([
                'status'                => 'approved',
                'approved_by'            => $actor?->id,
                'approved_at'            => now(),
                'old_schedule_version'    => $oldVersion,
                'new_schedule_version'    => $newVersion,
            ]);

            $this->audit->log(
                action: 'approved',
                module: 'loan_restructures',
                auditable: $restructure,
                after: ['status' => 'approved', 'loan_transaction_id' => $transaction->id],
                description: "Restructure of loan '{$loan->account_number}' was approved by '{$actor?->username}'.",
            );

            $customer = $loan->customer;
            $this->notifications->notifyCustomerEmail(
                actor: $actor,
                customer: $customer,
                type: 'loan_restructure_approved',
                subject: 'Your Loan Has Been Restructured',
                body: "Dear " . trim("{$customer?->first_name} {$customer?->last_name}") . ",<br><br>"
                    . "Your loan on account <strong>{$loan->account_number}</strong> has been restructured.<br><br>"
                    . "Outstanding Principal: <strong>" . number_format((float) $loan->outstanding_principal, 2) . "</strong><br>"
                    . "Outstanding Interest: <strong>" . number_format((float) $loan->outstanding_interest, 2) . "</strong><br><br>"
                    . "A new repayment schedule has been generated for your loan. Please contact us if you have any questions.",
            );

            return $restructure->fresh();
        });
    }

    public function reject(LoanRestructure $restructure, string $reason): LoanRestructure
    {
        if ($restructure->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Only a pending restructure can be rejected.']]);
        }

        $actor = Auth::guard('user')->user();

        $restructure->update([
            'status'             => 'rejected',
            'rejected_by'         => $actor?->id,
            'rejected_at'         => now(),
            'rejection_reason'    => $reason,
        ]);

        $this->audit->log(
            action: 'rejected',
            module: 'loan_restructures',
            auditable: $restructure,
            after: ['status' => 'rejected', 'reason' => $reason],
            description: "Restructure request for loan '{$restructure->loan?->account_number}' was rejected by '{$actor?->username}': {$reason}",
        );

        $customer = $restructure->loan?->customer;
        $this->notifications->notifyCustomerEmail(
            actor: $actor,
            customer: $customer,
            type: 'loan_restructure_rejected',
            subject: 'Your Loan Restructure Request Was Declined',
            body: "Dear " . trim("{$customer?->first_name} {$customer?->last_name}") . ",<br><br>"
                . "Your restructure request on loan account <strong>{$restructure->loan?->account_number}</strong> was declined.<br><br>"
                . "Reason: <strong>{$reason}</strong><br><br>"
                . "Please contact us if you have any questions.",
        );

        return $restructure;
    }
}
