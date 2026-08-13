<?php

declare(strict_types=1);

namespace App\Services\Loan;

use App\Models\Loan;
use App\Models\LoanCollection;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Auth;

class LoanCollectionService
{
    public function __construct(
        private AuditService $audit,
    ) {}

    public function listForLoan(Loan $loan)
    {
        return $loan->collectionActivities()->with('collectionOfficer')->latest()->get();
    }

    // Pending/overdue promises across the whole portfolio — the collections team's queue.
    public function pendingPromises(int $perPage = 50)
    {
        return LoanCollection::query()
            ->whereNotNull('promise_date')
            ->whereNull('promise_kept')
            ->where('promise_date', '<=', now()->toDateString())
            ->with(['loan.customer', 'collectionOfficer'])
            ->orderBy('promise_date')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function log(Loan $loan, array $data): LoanCollection
    {
        $actor = Auth::guard('user')->user();

        $activity = $loan->collectionActivities()->create([
            ...$data,
            'days_past_due' => $loan->days_in_arrears,
            'created_by'     => $actor?->id,
        ]);

        $this->audit->log(
            action: 'created',
            module: 'loan_collections',
            auditable: $activity,
            after: $activity->toArray(),
            description: "Collection activity ({$activity->contact_method}, {$activity->outcome}) logged for loan '{$loan->account_number}' by '{$actor?->username}'.",
        );

        return $activity;
    }

    // Marks whether a past promise-to-pay was actually kept — called manually by a
    // collections officer reviewing the queue, or could be automated later by checking
    // whether a repayment landed by promise_date.
    public function markPromiseOutcome(LoanCollection $activity, bool $kept): LoanCollection
    {
        $activity->update(['promise_kept' => $kept]);

        return $activity;
    }
}
