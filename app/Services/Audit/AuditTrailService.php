<?php

namespace App\Services\Audit;

use App\Models\AuditTrail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditTrailService
{
    /**
     * Columns searched against as free text when a `search` term is given —
     * covers every human-readable field on the audit trail, including the
     * JSON before/after snapshots (MySQL allows LIKE on JSON columns).
     */
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
