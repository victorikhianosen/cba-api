<?php

namespace App\Services\GeneralLedger;

use App\Models\Currency;
use App\Models\GeneralLedger;
use App\Services\Audit\AuditService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return GeneralLedger::query()->latest()->paginate($perPage)->withQueryString();
    }

    public function find(int $id): GeneralLedger
    {
        return GeneralLedger::findOrFail($id);
    }

    public function create(array $data): GeneralLedger
    {
        $actor = auth()->user();

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
        $actor  = auth()->user();

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
        $actor  = auth()->user();

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
        $actor  = auth()->user();

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
