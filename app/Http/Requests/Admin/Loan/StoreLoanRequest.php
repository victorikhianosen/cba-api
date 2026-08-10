<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loan;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'loan_product_id'          => ['required', 'integer', 'exists:loan_products,id'],
            'disbursement_account_id'  => ['required', 'integer', 'exists:accounts,id'],
            'principal_amount'         => ['required', 'numeric', 'min:0.01'],
            'number_of_repayments'     => ['required', 'integer', 'min:1'],
            // Optional overrides — default to the disbursement account's own customer's
            // branch/account officer when omitted (see LoanService::open()).
            'branch_id'                => ['nullable', 'integer', 'exists:branches,id'],
            'loan_officer_id'          => ['nullable', 'integer', 'exists:account_officers,id'],
        ];
    }
}
