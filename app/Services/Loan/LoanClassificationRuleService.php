<?php

declare(strict_types=1);

namespace App\Services\Loan;

use App\Models\LoanClassificationRule;
use App\Services\Audit\AuditService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

// Loan NPL/PAR classification thresholds as editable data, not a hardcoded enum — a bank's
// classification policy is regulatory/operational, not something that should need a code
// deploy to retune. classify() is the method Phase 2's overdue-accrual cron will call once
// it exists; scoped now so that cron doesn't need a redesign later.
class LoanClassificationRuleService
{
    public function __construct(
        private AuditService $audit,
    ) {}

    public function list(): LengthAwarePaginator
    {
        return LoanClassificationRule::query()
            ->orderBy('sort_order')
            ->paginate(50);
    }

    public function find(int $id): LoanClassificationRule
    {
        return LoanClassificationRule::findOrFail($id);
    }

    public function create(array $data): LoanClassificationRule
    {
        $actor = Auth::guard('user')->user();
        $rule  = LoanClassificationRule::create($data)->refresh();

        $this->audit->log(
            action: 'created',
            module: 'loan_classification_rules',
            auditable: $rule,
            after: $rule->toArray(),
            description: "Loan classification rule '{$rule->label}' was created by '{$actor?->username}'.",
        );

        return $rule;
    }

    public function update(LoanClassificationRule $rule, array $data): LoanClassificationRule
    {
        $before = $rule->toArray();
        $actor  = Auth::guard('user')->user();

        $rule->update($data);
        $rule->refresh();

        $this->audit->log(
            action: 'updated',
            module: 'loan_classification_rules',
            auditable: $rule,
            before: $before,
            after: $rule->toArray(),
            description: "Loan classification rule '{$rule->label}' was updated by '{$actor?->username}'.",
        );

        return $rule;
    }

    public function delete(LoanClassificationRule $rule): void
    {
        $label = $rule->label;
        $actor = Auth::guard('user')->user();

        $rule->delete();

        $this->audit->log(
            action: 'deleted',
            module: 'loan_classification_rules',
            auditable: $rule,
            before: ['label' => $label],
            description: "Loan classification rule '{$label}' was deleted by '{$actor?->username}'.",
        );
    }

    // First active rule (by sort_order) whose [min_days_overdue, max_days_overdue] range
    // contains $daysInArrears — max_days_overdue = null means unbounded (the worst bucket).
    public function classify(int $daysInArrears): ?LoanClassificationRule
    {
        return LoanClassificationRule::query()
            ->where('status', 'active')
            ->where('min_days_overdue', '<=', $daysInArrears)
            ->where(function ($query) use ($daysInArrears) {
                $query->whereNull('max_days_overdue')
                    ->orWhere('max_days_overdue', '>=', $daysInArrears);
            })
            ->orderBy('sort_order')
            ->first();
    }
}
