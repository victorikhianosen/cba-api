<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditTrail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditTrailService
{
    // Columns searched as free text when a `search` term is given — covers every human-readable field, including JSON before/after snapshots (MySQL allows LIKE on JSON).
    private const SEARCHABLE_COLUMNS = [
        'description', 'module', 'actions', 'performed_by_name', 'performed_by_type',
        'ip', 'agent', 'channel', 'tenant_code', 'before_change', 'after_change',
    ];

    public function list(int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        return AuditTrail::query()
            ->when($search, fn ($query) => $query->where(function ($q) use ($search) {
                foreach (self::SEARCHABLE_COLUMNS as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): AuditTrail
    {
        return AuditTrail::findOrFail($id);
    }
}
