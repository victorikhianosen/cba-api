<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\CashierSession;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Shared shape for both buy and sell — the action (buy vs sell) is decided by which
// controller route is hit, not by a field in the payload.
class CashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'counterparty_type' => ['required', Rule::in(['vault', 'teller'])],
            'counterparty_id'   => ['required', 'integer'],
            'amount'             => ['required', 'numeric', 'min:0.01'],
            'narration'          => ['nullable', 'string', 'max:255'],
        ];
    }
}
