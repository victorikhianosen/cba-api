<?php

declare(strict_types=1);

namespace App\Services\GeneralLedger;

use App\Enums\GeneralLedger\GeneralLedgerStatus;
use App\Models\Currency;
use App\Models\GeneralLedger;
use App\Services\Audit\AuditService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GeneralLedgerService
{
    private const CLASSIFICATION_BASE_CODE = [
        'asset'     => 10_000_000,
        'liability' => 20_000_000,
        'equity'    => 30_000_000,
        'income'    => 40_000_000,
        'expense'   => 50_000_000,
    ];

    public function __construct(
        private AuditService $audit,
    ) {}

    // $search matches the GL's name or gl_code — lets a search-as-you-type picker work
    // against however many ledgers exist without ever loading them all client-side.
    public function list(int $perPage = 50, ?string $search = null, ?string $status = null, ?bool $isAssigned = null, ?int $currencyId = null): LengthAwarePaginator
    {
        return GeneralLedger::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('gl_code', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->when(! is_null($isAssigned), fn ($query) => $query->where('is_assigned', $isAssigned))
            ->when($currencyId, fn ($query, $currencyId) => $query->where('currency_id', $currencyId))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    // A cursor, not a Collection — never loads more than one row into memory at a time,
    // so exporting stays flat regardless of how many ledgers match the filter.
    public function cursorForExport(?string $search = null): \Illuminate\Support\LazyCollection
    {
        return GeneralLedger::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('gl_code', 'like', "%{$search}%");
                });
            })
            ->with('currency')
            ->orderBy('gl_code')
            ->cursor();
    }

    public function countForFilter(?string $search = null): int
    {
        return GeneralLedger::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('gl_code', 'like', "%{$search}%");
                });
            })
            ->count();
    }

    public function find(int $id): GeneralLedger
    {
        return GeneralLedger::findOrFail($id);
    }

    // Whole-table counts, independent of pagination/search, for summary cards.
    public function stats(): array
    {
        return [
            'total'      => GeneralLedger::count(),
            'active'     => GeneralLedger::where('status', GeneralLedgerStatus::ACTIVE->value)->count(),
            'inactive'   => GeneralLedger::where('status', GeneralLedgerStatus::INACTIVE->value)->count(),
            'assigned'   => GeneralLedger::where('is_assigned', true)->count(),
            'unassigned' => GeneralLedger::where('is_assigned', false)->count(),
        ];
    }

    public function available(int $perPage = 50): LengthAwarePaginator
    {
        return GeneralLedger::query()
            ->where('status', GeneralLedgerStatus::ACTIVE->value)
            ->whereDoesntHave('productMapping')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): GeneralLedger
    {
        $actor = Auth::guard('user')->user();

        $generalLedger = DB::transaction(function () use ($data) {
            $data['gl_code']       = $this->generateGlCode($data['classification']);
            $data['currency_code'] = $this->resolveCurrencyCode($data['currency_id'] ?? null);

            $generalLedger = GeneralLedger::create($data);

            $generalLedger->update([
                'hierarchy' => $this->buildHierarchy($generalLedger),
            ]);

            return $generalLedger->refresh();
        });

        $this->audit->log(
            action: 'created',
            module: 'general_ledgers',
            auditable: $generalLedger,
            after: $generalLedger->toArray(),
            description: "General ledger '{$generalLedger->name}' ({$generalLedger->gl_code}) was created by '{$actor?->username}'.",
        );

        return $generalLedger;
    }

    public function update(GeneralLedger $generalLedger, array $data): GeneralLedger
    {
        $before = $generalLedger->toArray();
        $actor  = Auth::guard('user')->user();

        DB::transaction(function () use ($generalLedger, $data) {
            unset($data['gl_code'], $data['currency_id'], $data['currency_code'], $data['status']);

            if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null && (int) $data['parent_id'] === $generalLedger->id) {
                throw ValidationException::withMessages([
                    'parent_id' => ['A general ledger cannot be its own parent.'],
                ]);
            }

            $generalLedger->update($data);

            if (array_key_exists('parent_id', $data)) {
                $generalLedger->update([
                    'hierarchy' => $this->buildHierarchy($generalLedger),
                ]);

                $this->realignDescendantHierarchies($generalLedger);
            }
        });

        $generalLedger->refresh();

        $this->audit->log(
            action: 'updated',
            module: 'general_ledgers',
            auditable: $generalLedger,
            before: $before,
            after: $generalLedger->toArray(),
            description: "General ledger '{$generalLedger->name}' ({$generalLedger->gl_code}) was updated by '{$actor?->username}'.",
        );

        return $generalLedger;
    }

    public function updateStatus(GeneralLedger $generalLedger, string $status): GeneralLedger
    {
        $before = $generalLedger->status;
        $actor  = Auth::guard('user')->user();

        $generalLedger->update(['status' => $status]);
        $generalLedger->refresh();

        $this->audit->log(
            action: 'status_updated',
            module: 'general_ledgers',
            auditable: $generalLedger,
            before: ['status' => $before],
            after: ['status' => $generalLedger->status],
            description: "General ledger '{$generalLedger->name}' ({$generalLedger->gl_code}) status changed from '{$before}' to '{$generalLedger->status}' by '{$actor?->username}'.",
        );

        return $generalLedger;
    }

    public function delete(GeneralLedger $generalLedger): void
    {
        $name   = $generalLedger->name;
        $glCode = $generalLedger->gl_code;
        $actor  = Auth::guard('user')->user();

        $generalLedger->delete();

        $this->audit->log(
            action: 'deleted',
            module: 'general_ledgers',
            auditable: $generalLedger,
            before: ['name' => $name, 'gl_code' => $glCode],
            description: "General ledger '{$name}' ({$glCode}) was deleted by '{$actor?->username}'.",
        );
    }

    private function generateGlCode(string $classification): string
    {
        $base       = self::CLASSIFICATION_BASE_CODE[$classification];
        $upperBound = $base + 9_999_999;

        $lastCode = GeneralLedger::whereBetween('gl_code', [(string) $base, (string) $upperBound])
            ->lockForUpdate()
            ->orderByDesc('gl_code')
            ->value('gl_code');

        return (string) ($lastCode ? ((int) $lastCode) + 1 : $base);
    }

    private function resolveCurrencyCode(?int $currencyId): ?string
    {
        if (! $currencyId) {
            return null;
        }

        return Currency::find($currencyId)?->code;
    }

    private function buildHierarchy(GeneralLedger $generalLedger): string
    {
        if (! $generalLedger->parent_id) {
            return (string) $generalLedger->gl_code;
        }

        $parent = GeneralLedger::find($generalLedger->parent_id);

        return $parent
            ? $parent->hierarchy . '/' . $generalLedger->gl_code
            : (string) $generalLedger->gl_code;
    }

    private function realignDescendantHierarchies(GeneralLedger $generalLedger): void
    {
        foreach ($generalLedger->children()->get() as $child) {
            $child->update(['hierarchy' => $this->buildHierarchy($child)]);
            $this->realignDescendantHierarchies($child);
        }
    }
}
