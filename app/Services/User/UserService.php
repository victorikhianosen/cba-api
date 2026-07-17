<?php

namespace App\Services\User;

use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Communication\CommunicationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserService
{
    public function __construct(
        private AuditService $audit,
        private CommunicationService $message,

    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return User::query()->with(['roles', 'permissions'])->latest()->paginate($perPage)->withQueryString();
    }

    public function find(int $id): User
    {
        return User::with(['roles', 'permissions'])->findOrFail($id);
    }



    public function create(array $data): User
    {
        $plainPassword = $data['password'];

        $data['code']     = $this->generateUniqueCode();
        $data['username'] = $data['username'] ?? $this->generateUniqueUsername($data['first_name'], $data['last_name']);
        $data['password'] = bcrypt($plainPassword);
        $data['status']   = 'pending';

        $user = User::create($data);

        // Deferred: audit log
        dispatch(function () use ($user) {
            $this->audit->log(
                action: 'created',
                module: 'users',
                auditable: $user,
                after: $user->only(['id', 'username', 'code', 'staff_code', 'email']),
                description: "New user account '{$user->username}' was created with code '{$user->code}' and staff code '{$user->staff_code}'.",
            );
        })->afterResponse();

        dispatch(function () use ($user, $plainPassword) {
            $this->message->sendEmail(
                actor: $user,
                type: 'account_created',
                recipient: $user->email,
                body: view('emails.bodies.user.created', [
                    'user'     => $user,
                    'username' => $user->username,
                    'password' => $plainPassword,
                ])->render(),
                subject: 'Your Account is Ready',
                payload: ['user_id' => $user->id, 'code' => $user->code],
            );
        })->afterResponse();

        return $user;
    }


    public function update(int $id, array $data): User
    {
        $user = $this->find($id);

        $trackedFields = ['username', 'email', 'branch_id', 'phone'];
        $before = $user->only($trackedFields);

        if (isset($data['password']) && $data['password'] !== '') {
            $data['password'] = bcrypt($data['password']);
        }

        $filteredData = array_filter($data, fn($value) => $value !== null && $value !== '');

        $user->update($filteredData);
        $user->refresh();

        $this->audit->log(
            action: 'updated',
            module: 'users',
            auditable: $user,
            before: $before,
            after: $user->only($trackedFields),
            description: "User '{$user->username}' ({$user->code}) updated.",
        );

        return $user;
    }

    public function resetPassword(int $id): User
    {
        $user = $this->find($id);

        $plainPassword = Str::password(
            length: 12,
            letters: true,
            numbers: true,
            symbols: false,
            spaces: false,
        );

        $user->update([
            'password'       => bcrypt($plainPassword),
            'reset_password' => true,
        ]);
        $user->refresh();

        $actor = auth()->user();

        $this->audit->log(
            action: 'password_reset',
            module: 'users',
            auditable: $user,
            description: "Password for user '{$user->username}' ({$user->code}) was reset by '{$actor?->username}'.",
        );

        dispatch(function () use ($user, $plainPassword) {
            $this->message->sendEmail(
                actor: $user,
                type: 'password_reset',
                recipient: $user->email,
                body: view('emails.bodies.user.password_reset', [
                    'user'     => $user,
                    'password' => $plainPassword,
                ])->render(),
                subject: 'Your Password Has Been Reset',
                payload: ['user_id' => $user->id, 'code' => $user->code],
            );
        })->afterResponse();

        return $user;
    }

    public function uploadProfilePicture(int $id, UploadedFile $file): User
    {
        $user = $this->find($id);
        $before = $user->profile_picture;

        $path = $file->store('profile-pictures', 's3');

        $user->update(['profile_picture' => $path]);
        $user->refresh();

        if ($before) {
            Storage::disk('s3')->delete($before);
        }

        $this->audit->log(
            action: 'profile_picture_updated',
            module: 'users',
            auditable: $user,
            before: ['profile_picture' => $before],
            after: ['profile_picture' => $user->profile_picture],
            description: "Profile picture for user '{$user->username}' ({$user->code}) was updated.",
        );

        return $user;
    }

    public function updateStatus(int $id, string $status): User
    {
        $user = $this->find($id);
        $before = $user->status;
        $user->update(['status' => $status]);
        $user->refresh();

        $this->audit->log(
            action: 'status_updated',
            module: 'users',
            auditable: $user,
            before: ['status' => $before],
            after: ['status' => $user->status],
            description: "User '{$user->username}' ({$user->code}) status changed from '{$before}' to '{$user->status}'.",
        );

        return $user;
    }

    private function generateUniqueCode(): string
    {
        $next = User::count() + 1;

        do {
            $code = 'USR' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            $next++;
        } while (User::where('code', $code)->exists());

        return $code;
    }


    private function generateUniqueUsername(string $firstName, string $lastName): string
    {
        $first = Str::slug($firstName, '');
        $full  = Str::slug($firstName . $lastName, '');

        // Try first name alone first
        if (! User::where('username', $first)->exists()) {
            return $first;
        }

        // Then try first name + last name together
        if (! User::where('username', $full)->exists()) {
            return $full;
        }

        // Then append numbers until unique, no dots
        $suffix = 1;
        do {
            $username = $full . $suffix;
            $suffix++;
        } while (User::where('username', $username)->exists());

        return $username;
    }


    public function syncRoles(User $user, array $roles): User
    {
        $user->syncRoles($this->resolveRoles($roles));

        return $user->refresh()->load(['roles', 'permissions']);
    }

    public function assignRoles(User $user, array $roles): User
    {
        $before = $user->roles->pluck('name');
        $actor  = auth()->user();

        $user->assignRole($this->resolveRoles($roles));
        $user->refresh()->load(['roles', 'permissions']);

        $this->audit->log(
            action: 'roles_assigned',
            module: 'users',
            auditable: $user,
            before: ['roles' => $before],
            after: ['roles' => $user->roles->pluck('name')],
            description: "Role(s) [" . implode(', ', $roles) . "] were assigned to user '{$user->username}' by '{$actor?->username}'.",
        );

        return $user;
    }

    public function removeRoles(User $user, array $roles): User
    {
        $before = $user->roles->pluck('name');
        $actor  = auth()->user();

        $user->removeRole($this->resolveRoles($roles));
        $user->refresh()->load(['roles', 'permissions']);

        $this->audit->log(
            action: 'roles_removed',
            module: 'users',
            auditable: $user,
            before: ['roles' => $before],
            after: ['roles' => $user->roles->pluck('name')],
            description: "Role(s) [" . implode(', ', $roles) . "] were removed from user '{$user->username}' by '{$actor?->username}'.",
        );

        return $user;
    }

    public function syncPermissions(User $user, array $permissions): User
    {
        $user->syncPermissions($this->resolvePermissions($permissions));

        return $user->refresh()->load('permissions');
    }

    /**
     * Resolve role names to `user`-guard model instances so Spatie's guard
     * auto-detection (which defaults to `web` for this app) is bypassed.
     */
    private function resolveRoles(array $names): \Illuminate\Support\Collection
    {
        return Role::whereIn('name', $names)->where('guard_name', 'user')->get();
    }

    private function resolvePermissions(array $names): \Illuminate\Support\Collection
    {
        return Permission::whereIn('name', $names)->where('guard_name', 'user')->get();
    }
}
