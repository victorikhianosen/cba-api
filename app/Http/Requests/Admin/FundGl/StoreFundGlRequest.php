<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\FundGl;

use Illuminate\Foundation\Http\FormRequest;

class StoreFundGlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_id'      => ['required', 'integer', 'exists:general_ledgers,id'],
            'destination_id' => ['required', 'integer', 'exists:general_ledgers,id'],

            // Decimal amounts, at most 2 decimal places (kobo/cents precision) —
            // 0.01 is the smallest fundable amount, 100.5 and 100.50 are fine,
            // 100.505 is not.
            'amount' => ['required', 'numeric', 'min:0.01', 'regex:/^\d+(\.\d{1,2})?$/'],

            'remarks' => ['required', 'string', 'max:1000'],
        ];
    }
}
