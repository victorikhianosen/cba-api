<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loan;

use Illuminate\Foundation\Http\FormRequest;

class RequestLoanRefinanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'loan_product_id'            => ['required', 'integer', 'exists:loan_products,id'],
            'additional_cash_out'        => ['nullable', 'numeric', 'min:0'],
            'disbursement_account_id'     => ['nullable', 'integer', 'exists:accounts,id'],
            'new_duration'   => ['nullable', 'integer', 'min:1', 'max:24'],
            'new_interest_rate'          => ['nullable', 'numeric', 'min:0'],
            'reason'                    => ['nullable', 'string', 'max:1000'],
        ];
    }
}
