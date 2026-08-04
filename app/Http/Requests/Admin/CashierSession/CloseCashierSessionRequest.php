<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\CashierSession;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashierSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'counted_closing_amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
