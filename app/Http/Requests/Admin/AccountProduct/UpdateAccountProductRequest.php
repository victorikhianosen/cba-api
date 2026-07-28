<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\AccountProduct;

use App\Rules\StrictInteger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Name is a free-text label ("Saving Account") — the unique machine identifier is the
        // auto-generated `code` (e.g. SAV000001), so name isn't forced into snake_case anymore.
        if ($this->filled('name')) {
            $this->merge([
                'name' => trim($this->name),
            ]);
        }

        if ($this->filled('interest_calculation_days_in_year')) {
            $this->merge([
                'interest_calculation_days_in_year' => (string) $this->interest_calculation_days_in_year,
            ]);
        }
    }

    public function rules(): array
    {
        $accountProductId = $this->route('id');

        return [
            'name'                               => ['nullable', 'string', 'max:255', Rule::unique('account_products', 'name')->ignore($accountProductId)],
            'description'                         => ['nullable', 'string'],
            'interest_rate'                       => ['nullable', 'numeric', 'min:0'],
            'interest_type'                       => ['nullable', Rule::in(['flat', 'daily_balance', 'average_daily_balance', 'tiered'])],
            'interest_compounding_period'          => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'annually'])],
            'interest_posting_period'             => ['nullable', Rule::in(['daily', 'monthly', 'quarterly', 'bi_annually', 'annually'])],
            'interest_calculation_days_in_year'     => ['nullable', Rule::in(['360', '365'])],
            'min_required_opening_balance'         => ['nullable', 'integer', new StrictInteger, 'min:0'],
            'min_required_balance'                => ['nullable', 'integer', new StrictInteger, 'min:0'],
            'enforce_min_required_balance'          => ['nullable', 'boolean'],
            'locking_period_frequency'             => ['nullable', 'integer', 'min:1'],
            'locking_period_frequency_type'        => ['nullable', Rule::in(['days', 'weeks', 'months', 'years'])],
            'allow_overdraft'                      => ['nullable', 'boolean'],
            'overdraft_limit'                      => ['nullable', 'integer', new StrictInteger, 'min:0'],
            'overdraft_interest_rate'             => ['nullable', 'numeric', 'min:0'],
            'withhold_tax'                         => ['nullable', 'boolean'],
            'is_lien_allowed'                      => ['nullable', 'boolean'],
            'max_allowed_lien_limit'               => ['nullable', 'integer', new StrictInteger, 'min:0'],
            'dormancy_period_days'                 => ['nullable', 'integer', 'min:1'],

            'withdrawal_fee_amount'               => ['nullable', 'integer', new StrictInteger, 'min:0'],
            'withdrawal_fee_type'                 => ['nullable', Rule::in(['flat', 'percent_of_amount'])],
            'annual_fee_amount'                    => ['nullable', 'integer', new StrictInteger, 'min:0'],
            'annual_fee_on_month'                  => ['nullable', 'integer', 'min:1', 'max:12'],
            'annual_fee_on_day'                    => ['nullable', 'integer', 'min:1', 'max:31'],
            'min_balance_for_interest_calculation' => ['nullable', 'integer', new StrictInteger, 'min:0'],
        ];
    }
}
