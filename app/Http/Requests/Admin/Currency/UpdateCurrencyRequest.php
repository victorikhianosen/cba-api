<?php

namespace App\Http\Requests\Admin\Currency;

use App\Models\Currency;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currencyId = $this->route('id');

        return [
            'code' => [
                'sometimes',
                'string',
                'max:10',
                function (string $attribute, mixed $value, Closure $fail) use ($currencyId) {
                    $normalized = Str::upper(trim($value));

                    $exists = Currency::where('code', $normalized)
                        ->where('id', '!=', $currencyId)
                        ->exists();

                    if ($exists) {
                        $fail("A currency with code '{$normalized}' already exists.");
                    }
                },
            ],
            'name' => [
                'sometimes',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($currencyId) {
                    $normalized = trim(preg_replace('/\s+/', ' ', $value));

                    $exists = Currency::whereRaw('LOWER(name) = ?', [Str::lower($normalized)])
                        ->where('id', '!=', $currencyId)
                        ->exists();

                    if ($exists) {
                        $fail("A currency named '{$normalized}' already exists.");
                    }
                },
            ],
            'symbol' => [
                'sometimes',
                'nullable',
                'string',
                'max:10',
                function (string $attribute, mixed $value, Closure $fail) use ($currencyId) {
                    $normalized = trim((string) $value);

                    if ($normalized === '') {
                        return;
                    }

                    $exists = Currency::where('symbol', $normalized)
                        ->where('id', '!=', $currencyId)
                        ->exists();

                    if ($exists) {
                        $fail("A currency with symbol '{$normalized}' already exists.");
                    }
                },
            ],
            'buy_rate'         => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'sell_rate'        => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'exchange_rate'    => ['sometimes', 'numeric', 'min:0'],
            'is_base_currency' => ['sometimes', 'boolean'],
        ];
    }
}
