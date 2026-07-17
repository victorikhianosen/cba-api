<?php

namespace App\Services\AccountOfficer;

use App\Models\AccountOfficer;
use App\Services\Audit\AuditService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class AccountOfficerService
{
    public function __construct(
        private AuditService $audit,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return AccountOfficer::query()->with(['branch', 'user'])->latest()->paginate($perPage)->withQueryString();
    }

    public function find(int $id): AccountOfficer
    {
        return AccountOfficer::with(['branch', 'user'])->findOrFail($id);
    }

    public function create(array $data): AccountOfficer
    {
        $actor = auth()->user();

        $data['email'] = Str::lower(trim($data['email']));
        $data['code']  = $this->generateUniqueCode();
        $data['status'] = 'active';

        $officer = AccountOfficer::create($data)->fresh(['branch', 'user']);

        $this->audit->log(
            action: 'created',
            module: 'account_officers',
            auditable: $officer,
            after: $officer->toArray(),
            description: "Account officer '{$officer->first_name} {$officer->last_name}' ({$officer->code}) was created by '{$actor?->username}'.",
        );

        return $officer;
    }

    public function update(AccountOfficer $officer, array $data): AccountOfficer
    {
        $before = $officer->toArray();
        $actor  = auth()->user();

        if (isset($data['email'])) {
            $data['email'] = Str::lower(trim($data['email']));
        }

        unset($data['code'], $data['status']);

        $officer->update($data);
        $officer->refresh()->load(['branch', 'user']);

        $this->audit->log(
            action: 'updated',
            module: 'account_officers',
            auditable: $officer,
            before: $before,
            after: $officer->toArray(),
            description: "Account officer '{$officer->first_name} {$officer->last_name}' ({$officer->code}) was updated by '{$actor?->username}'.",
        );

        return $officer;
    }

    public function updateStatus(AccountOfficer $officer, string $status): AccountOfficer
    {
        $before = $officer->status;
        $actor  = auth()->user();

        $officer->update(['status' => $status]);
        $officer->refresh();

        $this->audit->log(
            action: 'status_updated',
            module: 'account_officers',
            auditable: $officer,
            before: ['status' => $before],
            after: ['status' => $officer->status],
            description: "Account officer '{$officer->first_name} {$officer->last_name}' ({$officer->code}) status changed from '{$before}' to '{$officer->status}' by '{$actor?->username}'.",
        );

        return $officer;
    }

    public function delete(AccountOfficer $officer): void
    {
        $name  = "{$officer->first_name} {$officer->last_name}";
        $code  = $officer->code;
        $actor = auth()->user();

        $officer->delete();

        $this->audit->log(
            action: 'deleted',
            module: 'account_officers',
            auditable: $officer,
            before: ['name' => $name, 'code' => $code],
            description: "Account officer '{$name}' ({$code}) was deleted by '{$actor?->username}'.",
        );
    }

    private function generateUniqueCode(): string
    {
        $next = AccountOfficer::count() + 1;

        do {
            $code = 'AO' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            $next++;
        } while (AccountOfficer::where('code', $code)->exists());

        return $code;
    }
}
