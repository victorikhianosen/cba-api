<?php

namespace App\Services\Role;

use App\Services\Audit\AuditService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\RoleAlreadyExists;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    public function __construct(
        private AuditService $audit,
    ) {}

    public function list(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return Role::query()
            ->with('permissions')
            ->when($search, fn ($query) => $query->where('name', 'like', '%' . $this->normalizeName($search) . '%'))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): Role
    {
        return Role::with('permissions')->findOrFail($id);
    }


    // RoleService::create()
    public function create(array $data): Role
    {
        $name = $this->normalizeName($data['name']);
        $actor = auth()->user();

        try {
            $role = Role::create([
                'name'       => $name,
                'guard_name' => 'user',
            ]);
        } catch (RoleAlreadyExists $e) {
            throw ValidationException::withMessages([
                'name' => ["A role named '{$data['name']}' already exists."],
            ]);
        }

        $this->audit->log(
            action: 'created',
            module: 'roles',
            auditable: $role,
            after: ['name' => $role->name],
            description: "Role '{$role->name}' was created by '{$actor?->username}'.",
        );

        return $role->load('permissions');
    }
    public function update(Role $role, array $data): Role
    {
        $before = ['name' => $role->name];
        $actor  = auth()->user();

        $role->update([
            'name' => isset($data['name']) ? $this->normalizeName($data['name']) : $role->name,
        ]);

        $this->audit->log(
            action: 'updated',
            module: 'roles',
            auditable: $role,
            before: $before,
            after: ['name' => $role->name],
            description: "Role '{$before['name']}' was renamed to '{$role->name}' by '{$actor?->username}'.",
        );

        return $role->refresh()->load('permissions');
    }



    public function delete(int $id): void
    {
        $role  = $this->find($id);
        $name  = $role->name;
        $actor = auth()->user();

        $role->delete();

        $this->audit->log(
            action: 'deleted',
            module: 'roles',
            auditable: $role,
            before: ['name' => $name],
            description: "Role '{$name}' was deleted by '{$actor?->username}'.",
        );
    }

    public function syncPermissions(Role $role, array $permissions): Role
    {
        $before = $role->permissions->pluck('name');
        $actor  = auth()->user();

        $role->syncPermissions($permissions);
        $role->refresh()->load('permissions');

        $this->audit->log(
            action: 'permissions_synced',
            module: 'roles',
            auditable: $role,
            before: ['permissions' => $before],
            after: ['permissions' => $role->permissions->pluck('name')],
            description: "Permissions for role '{$role->name}' were set to [" . $role->permissions->pluck('name')->implode(', ') . "] by '{$actor?->username}'.",
        );

        return $role;
    }

    private function normalizeName(string $name): string
    {
        return Str::snake(trim($name));
    }
}
