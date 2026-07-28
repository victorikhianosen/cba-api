<?php

declare(strict_types=1);

namespace App\Services\JournalEntry;

use App\Models\JournalEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

class JournalEntryService
{
    public function list(int $perPage = 50, ?string $from = null, ?string $to = null, ?int $generalLedgerId = null, ?string $search = null): LengthAwarePaginator
    {
        return $this->filtered($from, $to, $generalLedgerId, $search)
            ->with(['generalLedger', 'currency'])
            ->latest('posting_date')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    // A cursor, not a Collection — never loads more than one row into memory at a time,
    // so exporting stays flat regardless of how many entries match the filter.
    public function cursorForExport(?string $from = null, ?string $to = null, ?int $generalLedgerId = null, ?string $search = null): LazyCollection
    {
        return $this->filtered($from, $to, $generalLedgerId, $search)
            ->with(['generalLedger', 'currency'])
            ->orderBy('posting_date')
            ->orderBy('id')
            ->cursor();
    }

    public function countForFilter(?string $from = null, ?string $to = null, ?int $generalLedgerId = null, ?string $search = null): int
    {
        return $this->filtered($from, $to, $generalLedgerId, $search)->count();
    }

    private function filtered(?string $from, ?string $to, ?int $generalLedgerId, ?string $search = null): Builder
    {
        return JournalEntry::query()
            ->when($from, fn ($query, $from) => $query->whereDate('posting_date', '>=', $from))
            ->when($to, fn ($query, $to) => $query->whereDate('posting_date', '<=', $to))
            ->when($generalLedgerId, fn ($query, $id) => $query->where('general_ledger_id', $id))
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('journal_reference', 'like', "%{$search}%")
                        ->orWhere('transaction_reference', 'like', "%{$search}%")
                        ->orWhere('narration', 'like', "%{$search}%")
                        ->orWhereHas('generalLedger', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('gl_code', 'like', "%{$search}%");
                        });
                });
            });
    }
}
