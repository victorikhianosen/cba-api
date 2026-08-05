<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Enums\Account\LienStatus;
use App\Models\Account;
use App\Models\AccountLien;
use App\Services\Audit\AuditService;
use App\Services\Notification\NotificationService;
use App\Support\ReferenceGenerator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;

// Liens go through a maker-checker flow: place() only ever creates a pending record — the
// account's lien_balance is untouched until approve() (or reversed on release()). This
// mirrors PostingService's approve()/reject() shape.
class AccountLienService
{
    private const RELATIONS = ['account', 'customer', 'currency', 'createdBy', 'approvedBy', 'rejectedBy', 'releaseBy'];

    public function __construct(
        private AuditService $audit,
        private NotificationService $notifications,
    ) {}

    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->filtered($filters['search'] ?? null, $filters['status'] ?? null)
            ->with(self::RELATIONS)
            ->when($filters['account_id'] ?? null, fn ($query, $accountId) => $query->where('account_id', $accountId))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): AccountLien
    {
        return AccountLien::with(self::RELATIONS)->findOrFail($id);
    }

    public function forAccount(int $accountId, int $perPage = 15): LengthAwarePaginator
    {
        return AccountLien::with(self::RELATIONS)
            ->where('account_id', $accountId)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function cursorForExport(?string $search = null, ?string $status = null): LazyCollection
    {
        return $this->filtered($search, $status)
            ->with(self::RELATIONS)
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursor();
    }

    public function countForFilter(?string $search = null, ?string $status = null): int
    {
        return $this->filtered($search, $status)->count();
    }

    // Real, table-wide totals — independent of whatever page/filter is currently applied,
    // so the UI's summary cards never lie about what's actually in the table.
    public function stats(): array
    {
        return [
            'total'          => AccountLien::query()->count(),
            'total_amount'   => (string) AccountLien::query()->sum('amount'),
            'pending'        => AccountLien::query()->where('status', LienStatus::PENDING->value)->count(),
            'pending_amount' => (string) AccountLien::query()->where('status', LienStatus::PENDING->value)->sum('amount'),
            'active'         => AccountLien::query()->where('status', LienStatus::ACTIVE->value)->count(),
            'active_amount'  => (string) AccountLien::query()->where('status', LienStatus::ACTIVE->value)->sum('amount'),
            'rejected'       => AccountLien::query()->where('status', LienStatus::REJECTED->value)->count(),
            'released'       => AccountLien::query()->where('status', LienStatus::RELEASED->value)->count(),
            'expired'        => AccountLien::query()->where('status', LienStatus::EXPIRED->value)->count(),
        ];
    }

    private function filtered(?string $search = null, ?string $status = null): Builder
    {
        return AccountLien::query()
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('reference', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%");
                });
            });
    }

    public function place(int $accountId, string $amount, ?string $reason = null, ?string $remarks = null, ?string $expiresAt = null): AccountLien
    {
        $account = Account::with('customer')->findOrFail($accountId);
        $actor   = Auth::guard('user')->user();

        $lien = AccountLien::create([
            'reference'      => ReferenceGenerator::generate('LIEN'),
            'account_id'     => $account->id,
            'customer_id'    => $account->customer_id,
            'account_number' => $account->account_number,
            'currency_id'    => $account->currency_id,
            'amount'         => $amount,
            'reason'         => $reason,
            'remarks'        => $remarks,
            'status'         => LienStatus::PENDING->value,
            'created_by'     => $actor?->id,
            'expires_at'     => $expiresAt,
            'branch_id'      => $account->customer?->branch_id,
        ]);

        $this->audit->log(
            action: 'lien_requested',
            module: 'account_liens',
            auditable: $lien,
            after: ['status' => $lien->status, 'amount' => $amount],
            description: "A lien of {$amount} was requested on account '{$account->account_number}' by '{$actor?->username}'.",
        );

        $this->notifications->notifyPermission(
            'approve_liens',
            'lien',
            $lien->id,
            'New lien request',
            "A lien of {$amount} was requested on account '{$account->account_number}' and is awaiting your approval.",
        );

        return $lien->load(self::RELATIONS);
    }

    public function approve(int $id): AccountLien
    {
        $lien  = AccountLien::findOrFail($id);
        $actor = Auth::guard('user')->user();

        if ($lien->status !== LienStatus::PENDING->value) {
            throw ValidationException::withMessages([
                'status' => ['Only pending liens can be approved.'],
            ]);
        }

        if ($actor && (int) $lien->created_by === (int) $actor->id) {
            throw ValidationException::withMessages([
                'status' => ['You cannot approve a lien you requested yourself.'],
            ]);
        }

        DB::transaction(function () use ($lien, $actor) {
            $lockedLien = AccountLien::whereKey($lien->id)->lockForUpdate()->firstOrFail();

            if ($lockedLien->status !== LienStatus::PENDING->value) {
                throw ValidationException::withMessages([
                    'status' => ['Only pending liens can be approved.'],
                ]);
            }

            $account = Account::whereKey($lockedLien->account_id)->lockForUpdate()->firstOrFail();

            $newLien = bcadd((string) $account->lien_balance, (string) $lockedLien->amount, 4);

            $account->update([
                'lien_balance'      => $newLien,
                'available_balance' => bcsub((string) $account->account_balance, $newLien, 4),
            ]);

            $lockedLien->update([
                'status'      => LienStatus::ACTIVE->value,
                'approved_by' => $actor?->id,
                'approved_at' => now(),
            ]);
        });

        $lien->refresh();

        $this->audit->log(
            action: 'lien_approved',
            module: 'account_liens',
            auditable: $lien,
            after: ['status' => $lien->status],
            description: "Lien '{$lien->reference}' was approved by '{$actor?->username}'.",
        );

        $this->notifications->notifyUser(
            $lien->createdBy,
            'lien',
            $lien->id,
            'Lien approved',
            "Your lien request '{$lien->reference}' was approved.",
        );

        return $lien->load(self::RELATIONS);
    }

    public function reject(int $id, string $reason): AccountLien
    {
        $lien  = AccountLien::findOrFail($id);
        $actor = Auth::guard('user')->user();

        DB::transaction(function () use ($lien, $reason, $actor) {
            $lockedLien = AccountLien::whereKey($lien->id)->lockForUpdate()->firstOrFail();

            if ($lockedLien->status !== LienStatus::PENDING->value) {
                throw ValidationException::withMessages([
                    'status' => ['Only pending liens can be rejected.'],
                ]);
            }

            $lockedLien->update([
                'status'      => LienStatus::REJECTED->value,
                'rejected_by' => $actor?->id,
                'rejected_at' => now(),
                'remarks'     => $reason,
            ]);
        });

        $lien->refresh();

        $this->audit->log(
            action: 'lien_rejected',
            module: 'account_liens',
            auditable: $lien,
            after: ['status' => $lien->status, 'remarks' => $reason],
            description: "Lien '{$lien->reference}' was rejected by '{$actor?->username}': {$reason}",
        );

        $this->notifications->notifyUser(
            $lien->createdBy,
            'lien',
            $lien->id,
            'Lien rejected',
            "Your lien request '{$lien->reference}' was rejected: {$reason}",
        );

        return $lien->load(self::RELATIONS);
    }

    public function release(int $id, ?string $reason = null): AccountLien
    {
        $lien  = AccountLien::findOrFail($id);
        $actor = Auth::guard('user')->user();

        DB::transaction(function () use ($lien, $reason, $actor) {
            $lockedLien = AccountLien::whereKey($lien->id)->lockForUpdate()->firstOrFail();

            if ($lockedLien->status !== LienStatus::ACTIVE->value) {
                throw ValidationException::withMessages([
                    'status' => ['Only active liens can be released.'],
                ]);
            }

            $account = Account::whereKey($lockedLien->account_id)->lockForUpdate()->firstOrFail();

            $remainingLien = bcsub((string) $account->lien_balance, (string) $lockedLien->amount, 4);
            if (bccomp($remainingLien, '0', 4) < 0) {
                $remainingLien = '0.0000';
            }

            $account->update([
                'lien_balance'      => $remainingLien,
                'available_balance' => bcsub((string) $account->account_balance, $remainingLien, 4),
            ]);

            $lockedLien->update([
                'status'          => LienStatus::RELEASED->value,
                'release_by'      => $actor?->id,
                'released_at'     => now(),
                'release_reason'  => $reason,
            ]);
        });

        $lien->refresh();

        $this->audit->log(
            action: 'lien_released',
            module: 'account_liens',
            auditable: $lien,
            after: ['status' => $lien->status, 'release_reason' => $reason],
            description: "Lien '{$lien->reference}' was released by '{$actor?->username}'" . ($reason ? ": {$reason}" : '.'),
        );

        return $lien->load(self::RELATIONS);
    }
}
