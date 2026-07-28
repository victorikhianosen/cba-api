<?php

declare(strict_types=1);

namespace App\Services\FinancialTransaction;

use App\Models\FinancialTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

class FinancialTransactionService
{
    public function list(int $perPage = 50, ?string $from = null, ?string $to = null, ?string $status = null, ?string $search = null): LengthAwarePaginator
    {
        return $this->filtered($from, $to, $status, $search)
            ->with(['customer', 'currency'])
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    // A cursor, not a Collection — never loads more than one row into memory at a time,
    // so exporting stays flat regardless of how many transactions match the filter.
    public function cursorForExport(?string $from = null, ?string $to = null, ?string $status = null, ?string $search = null): LazyCollection
    {
        return $this->filtered($from, $to, $status, $search)
            ->with(['customer', 'currency'])
            ->orderBy('created_at')
            ->cursor();
    }

    public function countForFilter(?string $from = null, ?string $to = null, ?string $status = null, ?string $search = null): int
    {
        return $this->filtered($from, $to, $status, $search)->count();
    }

    private function filtered(?string $from, ?string $to, ?string $status, ?string $search = null): Builder
    {
        return FinancialTransaction::query()
            ->when($from, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('transaction_reference', 'like', "%{$search}%")
                        ->orWhere('narration', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('business_name', 'like', "%{$search}%")
                                ->orWhere('cif_number', 'like', "%{$search}%");
                        });
                });
            });
    }
}
