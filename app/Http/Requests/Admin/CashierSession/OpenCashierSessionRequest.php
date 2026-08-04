<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\CashierSession;

use Illuminate\Foundation\Http\FormRequest;

class OpenCashierSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'teller_id'             => ['required', 'integer', 'exists:tellers,id'],
            'opening_float_amount'  => ['required', 'numeric', 'min:0'],
        ];
    }
}
