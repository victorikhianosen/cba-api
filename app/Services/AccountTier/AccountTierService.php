<?php

declare(strict_types=1);

namespace App\Services\AccountTier;

use App\Models\AccountTier;
use App\Services\Audit\AuditService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AccountTierService
{
    public function __construct(
        private AuditService $audit,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return AccountTier::query()
            ->where('status', '!=', 'deleted')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): AccountTier
    {
        return AccountTier::findOrFail($id);
    }

    public function create(array $data): AccountTier
    {
        $actor = Auth::guard('user')->user();

        $data['status'] = 'active';

        $accountTier = AccountTier::create($data)->refresh();

        $this->audit->log(
            action: 'created',
            module: 'account_tiers',
            auditable: $accountTier,
            after: $accountTier->toArray(),
            description: "Account tier level '{$accountTier->level}' was created by '{$actor?->username}'.",
        );

        return $accountTier;
    }

    public function update(AccountTier $accountTier, array $data): AccountTier
    {
        $before = $accountTier->toArray();
        $actor  = Auth::guard('user')->user();

        unset($data['status']);

        $accountTier->update($data);
        $accountTier->refresh();

        $this->audit->log(
            action: 'updated',
            module: 'account_tiers',
            auditable: $accountTier,
            before: $before,
            after: $accountTier->toArray(),
            description: "Account tier level '{$accountTier->level}' was updated by '{$actor?->username}'.",
        );

        return $accountTier;
    }

    // Soft delete — marks the tier 'deleted' rather than removing the row, since real
    // accounts may still reference it (accounts.account_tier_id) and existing account
    // records shouldn't lose their tier history. list() filters these out, so a deleted
    // tier behaves as "gone" everywhere except direct lookup/audit trail.
    public function delete(AccountTier $accountTier): void
    {
        if ($accountTier->accounts()->exists()) {
            throw ValidationException::withMessages([
                'level' => ['This account tier is assigned to one or more accounts and cannot be deleted.'],
            ]);
        }

        $before = $accountTier->toArray();
        $actor  = Auth::guard('user')->user();

        $accountTier->update(['status' => 'deleted']);

        $this->audit->log(
            action: 'deleted',
            module: 'account_tiers',
            auditable: $accountTier,
            before: $before,
            after: ['status' => 'deleted'],
            description: "Account tier level '{$accountTier->level}' was deleted by '{$actor?->username}'.",
        );
    }
}
