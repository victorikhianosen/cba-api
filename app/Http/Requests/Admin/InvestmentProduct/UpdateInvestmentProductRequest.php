<?php

namespace App\Http\Requests\Admin\InvestmentProduct;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvestmentProductRequest extends FormRequest
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
        $investmentProductId = $this->route('id');

        return [
            'name'          => ['nullable', 'string', 'max:255', Rule::unique('investment_products', 'name')->ignore($investmentProductId)],
            'description'   => ['nullable', 'string'],
            'interest_rate' => ['nullable', 'numeric', 'min:0'],
            'interest_type'                   => ['nullable', Rule::in(['flat', 'daily_balance', 'average_daily_balance', 'tiered'])],
            'interest_compounding_period'     => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'annually'])],
            'interest_posting_period'         => ['nullable', Rule::in(['daily', 'monthly', 'quarterly', 'bi_annually', 'annually'])],
            'interest_calculation_days_in_year' => ['nullable', Rule::in(['360', '365'])],
            'min_required_opening_balance'    => ['nullable', 'numeric', 'min:0'],
            'min_required_balance'            => ['nullable', 'numeric', 'min:0'],
            'locking_period_frequency'        => ['nullable', 'integer', 'min:1'],
            'locking_period_frequency_type'   => ['nullable', Rule::in(['days', 'weeks', 'months', 'years'])],

            'min_deposit_term'                     => ['nullable', 'integer', 'min:1'],
            'max_deposit_term'                     => ['nullable', 'integer', 'min:1', 'gte:min_deposit_term'],
            'min_deposit_term_type'                 => ['nullable', Rule::in(['days', 'weeks', 'months', 'years'])],
            'max_deposit_term_type'                 => ['nullable', Rule::in(['days', 'weeks', 'months', 'years'])],
            'in_multiples_of_deposit_term'          => ['nullable', 'integer', 'min:1'],
            'in_multiples_of_deposit_term_type'     => ['nullable', Rule::in(['days', 'weeks', 'months', 'years'])],
            'min_deposit_amount'                    => ['nullable', 'numeric', 'min:0'],
            'max_deposit_amount'                    => ['nullable', 'numeric', 'min:0', 'gte:min_deposit_amount'],
            'pre_closure_penal_applicable'          => ['nullable', 'boolean'],
            'pre_closure_penal_interest'            => ['nullable', 'numeric', 'min:0', 'required_if:pre_closure_penal_applicable,true'],
            'pre_closure_penal_interest_on_type'    => ['nullable', Rule::in(['whole_term', 'till_preclosure_date']), 'required_if:pre_closure_penal_applicable,true'],
        ];
    }
}
