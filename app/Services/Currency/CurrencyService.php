<?php

namespace App\Services\Currency;

use App\Models\Currency;
use App\Services\Audit\AuditService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CurrencyService
{
    public function __construct(
        private AuditService $audit,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Currency::query()->latest()->paginate($perPage)->withQueryString();
    }

    public function find(int $id): Currency
    {
        return Currency::findOrFail($id);
    }

    public function create(array $data): Currency
    {
        $actor = auth()->user();

        $data['code'] = $this->normalizeCode($data['code']);
        $data['name'] = $this->normalizeName($data['name']);

        if (isset($data['symbol'])) {
            $data['symbol'] = $this->normalizeSymbol($data['symbol']);
        }

        $currency = DB::transaction(function () use ($data) {
            if (! empty($data['is_base_currency'])) {
                Currency::where('is_base_currency', true)->update(['is_base_currency' => false]);
            }

            return Currency::create($data)->refresh();
        });

        $this->audit->log(
            action: 'created',
            module: 'currencies',
            auditable: $currency,
            after: $currency->toArray(),
            description: "Currency '{$currency->name}' ({$currency->code}) was created by '{$actor?->username}'.",
        );

        return $currency;
    }

    public function update(Currency $currency, array $data): Currency
    {
        $before = $currency->toArray();
        $actor  = auth()->user();

        if (isset($data['code'])) {
            $data['code'] = $this->normalizeCode($data['code']);
        }

        if (isset($data['name'])) {
            $data['name'] = $this->normalizeName($data['name']);
        }

        if (isset($data['symbol'])) {
            $data['symbol'] = $this->normalizeSymbol($data['symbol']);
        }

        DB::transaction(function () use ($currency, $data) {
            if (! empty($data['is_base_currency'])) {
                Currency::where('id', '!=', $currency->id)
                    ->where('is_base_currency', true)
                    ->update(['is_base_currency' => false]);
            }

            $currency->update($data);
        });

        $currency->refresh();

        $this->audit->log(
            action: 'updated',
            module: 'currencies',
            auditable: $currency,
            before: $before,
            after: $currency->toArray(),
            description: "Currency '{$currency->name}' ({$currency->code}) was updated by '{$actor?->username}'.",
        );

        return $currency;
    }

    public function updateStatus(Currency $currency, string $status): Currency
    {
        $before = $currency->status;
        $actor  = auth()->user();

        $currency->update(['status' => $status]);
        $currency->refresh();

        $this->audit->log(
            action: 'status_updated',
            module: 'currencies',
            auditable: $currency,
            before: ['status' => $before],
            after: ['status' => $currency->status],
            description: "Currency '{$currency->name}' ({$currency->code}) status changed from '{$before}' to '{$currency->status}' by '{$actor?->username}'.",
        );

        return $currency;
    }

    public function delete(Currency $currency): void
    {
        $name  = $currency->name;
        $code  = $currency->code;
        $actor = auth()->user();

        $currency->delete();

        $this->audit->log(
            action: 'deleted',
            module: 'currencies',
            auditable: $currency,
            before: ['name' => $name, 'code' => $code],
            description: "Currency '{$name}' ({$code}) was deleted by '{$actor?->username}'.",
        );
    }

    private function normalizeCode(string $code): string
    {
        return Str::upper(trim($code));
    }

    private function normalizeName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    private function normalizeSymbol(string $symbol): string
    {
        return trim($symbol);
    }
}
