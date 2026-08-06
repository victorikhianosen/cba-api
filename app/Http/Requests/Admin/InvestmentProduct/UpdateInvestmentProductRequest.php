<?php

declare(strict_types=1);

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
            'short_name'    => ['nullable', 'string', 'max:45'],
            'description'   => ['nullable', 'string'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'regex:/^\d+(\.\d{1,2})?$/'],

            'allowed_interest_posting_periods'   => ['nullable', 'array'],
            'allowed_interest_posting_periods.*' => [Rule::in(['upfront', 'daily', 'weekly', 'monthly', 'quarterly', 'bi_annually', 'annually', 'bullet'])],

            'min_tenure_days'       => ['nullable', 'integer', 'min:1', 'max:365'],
            'max_tenure_days'       => ['nullable', 'integer', 'min:1', 'max:365', 'gte:min_tenure_days'],
            'min_deposit_amount'    => ['nullable', 'numeric', 'min:0'],
            'max_deposit_amount'    => ['nullable', 'numeric', 'min:0', 'gte:min_deposit_amount'],

            'auto_renew'                    => ['nullable', 'boolean'],
            'allow_top_up'                  => ['nullable', 'boolean'],
            'allow_partial_withdrawal'      => ['nullable', 'boolean'],
            'minimum_retention_percentage'  => ['nullable', 'numeric', 'min:0', 'max:100', 'regex:/^\d+(\.\d{1,2})?$/'],
            'allow_early_liquidation'       => ['nullable', 'boolean'],
            'early_liquidation_lock_days'   => ['nullable', 'integer', 'min:0'],
            'early_liquidation_penalty_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'regex:/^\d+(\.\d{1,2})?$/'],
            'withholding_tax_rate'          => ['nullable', 'numeric', 'min:0', 'max:100', 'regex:/^\d+(\.\d{1,2})?$/'],
            'is_default'                    => ['nullable', 'boolean'],
        ];
    }
}
