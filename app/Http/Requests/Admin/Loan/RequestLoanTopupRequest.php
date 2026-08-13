<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loan;

use Illuminate\Foundation\Http\FormRequest;

class RequestLoanTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'topup_amount'               => ['required', 'numeric', 'min:0.01'],
            'disbursement_account_id'     => ['nullable', 'integer', 'exists:accounts,id'],
            'new_duration'   => ['nullable', 'integer', 'min:1', 'max:24'],
            'new_interest_rate'          => ['nullable', 'numeric', 'min:0'],
            'reason'                    => ['nullable', 'string', 'max:1000'],
        ];
    }
}
