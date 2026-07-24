<?php

declare(strict_types=1);

namespace App\Services\AccountTier;

use App\Models\AccountTier;
use App\Services\Audit\AuditService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AccountTierService
{
    public function __construct(
        private AuditService $audit,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return AccountTier::query()->latest()->paginate($perPage)->withQueryString();
    }

    public function find(int $id): AccountTier
    {
        return AccountTier::findOrFail($id);
    }

    public function create(array $data): AccountTier
    {
        $actor = auth()->user();

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
        $actor  = auth()->user();

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
}
