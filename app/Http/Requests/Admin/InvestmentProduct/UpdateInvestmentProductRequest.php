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
            'icon'          => ['nullable', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'interest_rate' => ['nullable', 'numeric', 'min:0'],
            'interest_posting_period' => ['nullable', Rule::in(['upfront', 'daily', 'weekly', 'monthly', 'quarterly', 'bi_annually', 'annually', 'bullet'])],

            'tenure'                => ['nullable', 'integer', 'min:1'],
            'tenure_unit'           => ['nullable', Rule::in(['days', 'weeks', 'months', 'years'])],
            'min_deposit_amount'    => ['nullable', 'numeric', 'min:0'],
            'max_deposit_amount'    => ['nullable', 'numeric', 'min:0', 'gte:min_deposit_amount'],

            'auto_renew'                    => ['nullable', 'boolean'],
            'allow_top_up'                  => ['nullable', 'boolean'],
            'allow_partial_withdrawal'      => ['nullable', 'boolean'],
            'allow_early_liquidation'       => ['nullable', 'boolean'],
            'early_liquidation_lock_days'   => ['nullable', 'integer', 'min:0'],
            'early_liquidation_penalty_rate' => ['nullable', 'numeric', 'min:0'],
            'is_default'                    => ['nullable', 'boolean'],
        ];
    }
}
