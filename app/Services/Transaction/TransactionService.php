<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

class TransactionService
{
    public function list(int $perPage = 50, ?string $from = null, ?string $to = null, ?int $accountId = null, ?string $search = null, ?string $transactionType = null, ?string $direction = null): LengthAwarePaginator
    {
        return $this->filtered($from, $to, $accountId, $search, $transactionType, $direction)
            ->with(['account', 'customer', 'currency'])
            ->latest('posting_date')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    // A cursor, not a Collection — never loads more than one row into memory at a time,
    // so exporting stays flat regardless of how many transactions match the filter.
    public function cursorForExport(?string $from = null, ?string $to = null, ?int $accountId = null, ?string $search = null, ?string $transactionType = null, ?string $direction = null): LazyCollection
    {
        return $this->filtered($from, $to, $accountId, $search, $transactionType, $direction)
            ->with(['account', 'customer', 'currency'])
            ->orderBy('posting_date')
            ->orderBy('id')
            ->cursor();
    }

    public function countForFilter(?string $from = null, ?string $to = null, ?int $accountId = null, ?string $search = null, ?string $transactionType = null, ?string $direction = null): int
    {
        return $this->filtered($from, $to, $accountId, $search, $transactionType, $direction)->count();
    }

    // Every distinct transaction_type value in use — powers the type filter dropdown
    // without hardcoding the taxonomy anywhere (new products just show up automatically).
    public function distinctTransactionTypes(): array
    {
        return Transaction::query()
            ->whereNotNull('transaction_type')
            ->distinct()
            ->orderBy('transaction_type')
            ->pluck('transaction_type')
            ->all();
    }

    private function filtered(?string $from, ?string $to, ?int $accountId, ?string $search = null, ?string $transactionType = null, ?string $direction = null): Builder
    {
        return Transaction::query()
            ->when($from, fn ($query, $from) => $query->whereDate('posting_date', '>=', $from))
            ->when($to, fn ($query, $to) => $query->whereDate('posting_date', '<=', $to))
            ->when($accountId, fn ($query, $id) => $query->where('account_id', $id))
            ->when($transactionType, fn ($query, $type) => $query->where('transaction_type', $type))
            ->when($direction, fn ($query, $direction) => $query->where('direction', $direction))
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('narration', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('transaction_reference', 'like', "%{$search}%")
                        ->orWhere('provider_reference', 'like', "%{$search}%")
                        ->orWhere('counterparty_name', 'like', "%{$search}%")
                        ->orWhere('counterparty_account_number', 'like', "%{$search}%")
                        ->orWhereHas('account', function ($query) use ($search) {
                            $query->where('account_number', 'like', "%{$search}%");
                        })
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
