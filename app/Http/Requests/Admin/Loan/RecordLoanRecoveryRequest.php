<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loan;

use Illuminate\Foundation\Http\FormRequest;

class RecordLoanRecoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'               => ['required', 'numeric', 'min:0.01'],
            'funding_account_id'    => ['required', 'integer', 'exists:accounts,id'],
            'principal_portion'     => ['nullable', 'numeric', 'min:0'],
            'interest_portion'      => ['nullable', 'numeric', 'min:0'],
            'penalty_portion'       => ['nullable', 'numeric', 'min:0'],
            'fee_portion'           => ['nullable', 'numeric', 'min:0'],
            'channel'               => ['nullable', 'string', 'max:100'],
            'reference'             => ['nullable', 'string', 'max:255'],
            'recovery_officer_id'    => ['nullable', 'integer', 'exists:account_officers,id'],
            'recovered_at'          => ['nullable', 'date'],
            'notes'                => ['nullable', 'string', 'max:1000'],
        ];
    }
}
