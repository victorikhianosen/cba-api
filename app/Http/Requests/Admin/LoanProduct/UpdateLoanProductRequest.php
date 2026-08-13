<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\LoanProduct;

use App\Rules\StrictInteger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoanProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $loanProductId = $this->route('id');

        return [
            'name'          => ['nullable', 'string', 'max:255', Rule::unique('loan_products', 'name')->ignore($loanProductId)],
            'description'   => ['nullable', 'string'],
            'interest_rate' => ['nullable', 'numeric', 'min:0'],
            'management_fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'processing_fee_percentage'  => ['nullable', 'numeric', 'min:0', 'max:100'],

            'min_principal_amount'                 => ['nullable', 'integer', new StrictInteger, 'min:0'],
            'max_principal_amount'                 => ['nullable', 'integer', new StrictInteger, 'min:0', 'gte:min_principal_amount'],
            'interest_rate_frequency_type'          => ['nullable', Rule::in(['per_day', 'per_week', 'per_month', 'per_year'])],
            'interest_method'                      => ['nullable', Rule::in(['declining_balance', 'flat'])],
            'repayment_every'                      => ['nullable', 'integer', 'min:1'],
            'repayment_frequency_type'              => ['nullable', Rule::in(['days', 'weeks', 'months'])],
            'arrears_tolerance_amount'              => ['nullable', 'integer', new StrictInteger, 'min:0'],
            'penalty_interest_rate'                => ['nullable', 'numeric', 'min:0'],
            'payment_allocation_order'   => ['nullable', 'array', 'size:4'],
            'payment_allocation_order.*' => ['required_with:payment_allocation_order', 'distinct', Rule::in(['penalty', 'fee', 'interest', 'principal'])],
        ];
    }
}
