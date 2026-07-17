<?php

namespace App\Http\Requests\Admin\LoanProduct;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoanProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('name')) {
            $this->merge([
                'name' => strtolower(str_replace(' ', '_', trim($this->name))),
            ]);
        }
    }

    public function rules(): array
    {
        $loanProductId = $this->route('id');

        return [
            'name'          => ['nullable', 'string', 'max:255', Rule::unique('loan_products', 'name')->ignore($loanProductId)],
            'description'   => ['nullable', 'string'],
            'interest_rate' => ['nullable', 'numeric', 'min:0'],

            'min_principal_amount'                 => ['nullable', 'numeric', 'min:0'],
            'max_principal_amount'                 => ['nullable', 'numeric', 'min:0', 'gte:min_principal_amount'],
            'default_principal_amount'             => ['nullable', 'numeric', 'min:0'],
            'min_nominal_interest_rate_per_period' => ['nullable', 'numeric', 'min:0'],
            'max_nominal_interest_rate_per_period' => ['nullable', 'numeric', 'min:0', 'gte:min_nominal_interest_rate_per_period'],
            'interest_rate_frequency_type'          => ['nullable', Rule::in(['per_day', 'per_week', 'per_month', 'per_year'])],
            'interest_method'                      => ['nullable', Rule::in(['declining_balance', 'flat'])],
            'interest_calculation_period_type'      => ['nullable', Rule::in(['daily', 'same_as_repayment_period'])],
            'repayment_every'                      => ['nullable', 'integer', 'min:1'],
            'repayment_frequency_type'              => ['nullable', Rule::in(['days', 'weeks', 'months'])],
            'number_of_repayments'                 => ['nullable', 'integer', 'min:1'],
            'min_number_of_repayments'              => ['nullable', 'integer', 'min:1'],
            'max_number_of_repayments'              => ['nullable', 'integer', 'min:1', 'gte:min_number_of_repayments'],
            'amortization_method'                  => ['nullable', Rule::in(['equal_installments', 'equal_principal'])],
            'grace_on_principal_periods'            => ['nullable', 'integer', 'min:0'],
            'grace_on_interest_periods'             => ['nullable', 'integer', 'min:0'],
            'grace_interest_free_periods'           => ['nullable', 'integer', 'min:0'],
            'grace_on_arrears_ageing'               => ['nullable', 'integer', 'min:0'],
            'arrears_tolerance_amount'              => ['nullable', 'numeric', 'min:0'],
            'overdue_days_for_npa'                 => ['nullable', 'integer', 'min:0'],
            'days_in_month_type'                   => ['nullable', Rule::in(['actual', 'thirty'])],
            'interest_calculation_days_in_year'    => ['nullable', Rule::in(['360', '365'])],
        ];
    }
}
