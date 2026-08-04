<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\CashierSession;

use Illuminate\Foundation\Http\FormRequest;

// Shared shape for deposit and withdraw — the account is identified by number, same
// account-number-first lookup pattern used elsewhere in this codebase (investment
// funding, minor-guardian lookup).
class CashTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_number' => ['required', 'string'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'narration'       => ['nullable', 'string', 'max:255'],
        ];
    }
}
