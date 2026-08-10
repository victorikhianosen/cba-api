<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loan;

use Illuminate\Foundation\Http\FormRequest;

class RepayLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'              => ['required', 'numeric', 'min:0.01'],
            'funding_account_id'  => ['required', 'integer', 'exists:accounts,id'],
            'description'          => ['nullable', 'string', 'max:255'],
        ];
    }
}
