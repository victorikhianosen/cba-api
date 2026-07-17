<?php

namespace App\Services\Permission;

use App\Services\Audit\AuditService;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Models\Permission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PermissionService
{
    public function __construct(
        private AuditService $audit,
    ) {}

    public function list(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return Permission::query()
            ->when($search, fn ($query) => $query->where('name', 'like', '%' . $this->normalizeName($search) . '%'))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): Permission
    {
        return Permission::findOrFail($id);
    }

    public function create(array $data): Permission
    {
        $name  = $this->normalizeName($data['name']);
        $actor = auth()->user();

        try {
            $permission = Permission::create([
                'name'       => $name,
                'guard_name' => 'user',
            ]);
        } catch (PermissionAlreadyExists $e) {
            throw ValidationException::withMessages([
                'name' => ["A permission named '{$data['name']}' already exists."],
            ]);
        }

        $this->audit->log(
            action: 'created',
            module: 'permissions',
            auditable: $permission,
            after: ['name' => $permission->name],
            description: "Permission '{$permission->name}' was created by '{$actor?->username}'.",
        );

        return $permission;
    }

    public function update(Permission $permission, array $data): Permission
    {
        if (isset($data['name'])) {
            $before = $permission->name;
            $name   = $this->normalizeName($data['name']);

            $duplicate = Permission::where('name', $name)
                ->where('guard_name', $permission->guard_name)
                ->where('id', '!=', $permission->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'name' => ["A permission named '{$data['name']}' already exists."],
                ]);
            }

            $permission->update(['name' => $name]);

            $actor = auth()->user();

            $this->audit->log(
                action: 'updated',
                module: 'permissions',
                auditable: $permission,
                before: ['name' => $before],
                after: ['name' => $permission->name],
                description: "Permission '{$before}' was renamed to '{$permission->name}' by '{$actor?->username}'.",
            );
        }

        return $permission->refresh();
    }

    public function delete(Permission $permission): void
    {
        $name  = $permission->name;
        $actor = auth()->user();

        $permission->delete();

        $this->audit->log(
            action: 'deleted',
            module: 'permissions',
            auditable: $permission,
            before: ['name' => $name],
            description: "Permission '{$name}' was deleted by '{$actor?->username}'.",
        );
    }

    private function normalizeName(string $name): string
    {
        return Str::snake(trim($name));
    }
}