<?php

namespace App\Http\Requests\Admin\Currency;

use App\Models\Currency;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:10',
                function (string $attribute, mixed $value, Closure $fail) {
                    $normalized = Str::upper(trim($value));

                    if (Currency::where('code', $normalized)->exists()) {
                        $fail("A currency with code '{$normalized}' already exists.");
                    }
                },
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) {
                    $normalized = trim(preg_replace('/\s+/', ' ', $value));

                    if (Currency::whereRaw('LOWER(name) = ?', [Str::lower($normalized)])->exists()) {
                        $fail("A currency named '{$normalized}' already exists.");
                    }
                },
            ],
            'symbol' => [
                'nullable',
                'string',
                'max:10',
                function (string $attribute, mixed $value, Closure $fail) {
                    $normalized = trim($value);

                    if ($normalized !== '' && Currency::where('symbol', $normalized)->exists()) {
                        $fail("A currency with symbol '{$normalized}' already exists.");
                    }
                },
            ],
            'buy_rate'         => ['nullable', 'numeric', 'min:0'],
            'sell_rate'        => ['nullable', 'numeric', 'min:0'],
            'exchange_rate'    => ['sometimes', 'numeric', 'min:0'],
            'is_base_currency' => ['sometimes', 'boolean'],
            'status'           => ['sometimes', 'in:active,inactive'],
        ];
    }
}
