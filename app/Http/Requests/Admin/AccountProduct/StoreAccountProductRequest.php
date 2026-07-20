<?php

namespace App\Http\Requests\Admin\AccountProduct;

use App\Enums\AccountProductType;
use App\Models\AccountProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Field names mirror Fineract's SavingProductAccountingParams
 * (fineract-core/.../accounting/common/AccountingConstants.java) for
 * cash-based savings/deposit accounting.
 */
class StoreAccountProductRequest extends FormRequest
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
        return [
            'name'                            => ['required', 'string', 'max:255', 'unique:account_products,name'],
            'product_type'                    => ['required', 'string', Rule::in(AccountProduct::PRODUCT_TYPES)],
            'description'                     => ['nullable', 'string'],
            'currency_id'                     => ['required', 'integer', 'exists:currencies,id'],
            'interest_rate'                   => ['nullable', 'numeric', 'min:0'],
            'interest_type'                   => ['nullable', Rule::in(['flat', 'daily_balance', 'average_daily_balance', 'tiered'])],
            'interest_compounding_period'     => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'annually'])],
            'interest_posting_period'         => ['nullable', Rule::in(['daily', 'monthly', 'quarterly', 'bi_annually', 'annually'])],
            'interest_calculation_days_in_year' => ['nullable', Rule::in(['360', '365'])],
            'min_required_opening_balance'    => ['nullable', 'numeric', 'min:0'],
            'min_required_balance'            => ['nullable', 'numeric', 'min:0'],
            'enforce_min_required_balance'    => ['nullable', 'boolean'],
            'locking_period_frequency'        => ['nullable', 'integer', 'min:1'],
            'locking_period_frequency_type'   => ['nullable', Rule::in(['days', 'weeks', 'months', 'years'])],
            'allow_overdraft'                 => ['nullable', 'boolean'],
            'overdraft_limit'                 => ['nullable', 'numeric', 'min:0'],
            'overdraft_interest_rate'         => ['nullable', 'numeric', 'min:0'],
            'withhold_tax'                    => ['nullable', 'boolean'],
            'is_lien_allowed'                 => ['nullable', 'boolean'],
            'max_allowed_lien_limit'          => ['nullable', 'numeric', 'min:0'],
            'dormancy_period_days'            => ['nullable', 'integer', 'min:1'],

            // Fee terms — Fineract m_savings_product field names.
            'withdrawal_fee_amount'               => ['nullable', 'numeric', 'min:0'],
            'withdrawal_fee_type'                 => ['nullable', Rule::in(['flat', 'percent_of_amount'])],
            'annual_fee_amount'                    => ['nullable', 'numeric', 'min:0'],
            'annual_fee_on_month'                  => ['nullable', 'integer', 'min:1', 'max:12'],
            'annual_fee_on_day'                    => ['nullable', 'integer', 'min:1', 'max:31'],
            'min_balance_for_interest_calculation' => ['nullable', 'numeric', 'min:0'],

            // Accounting (cash-based) — Fineract SavingProductAccountingParams field names.
            'savingsControlGLId'               => ['required', 'integer', 'exists:general_ledgers,id'],
            'interestOnSavingsGLId'            => ['required', 'integer', 'exists:general_ledgers,id'],
            'incomeFromFeeGLId'                => ['required', 'integer', 'exists:general_ledgers,id'],
            'incomeFromPenaltyGLId'            => ['required', 'integer', 'exists:general_ledgers,id'],
            'transfersInSuspenseGLId'          => ['required', 'integer', 'exists:general_ledgers,id'],
            'overdraftPortfolioGLId'           => ['required_if:allow_overdraft,true', 'nullable', 'integer', 'exists:general_ledgers,id'],
            'writeOffGLId'                     => ['nullable', 'integer', 'exists:general_ledgers,id'],
            'escheatLiabilityGLId'             => ['nullable', 'integer', 'exists:general_ledgers,id'],
            'incomeFromInterestGLId'           => ['nullable', 'integer', 'exists:general_ledgers,id'],
        ];
    }

    public function toAccountProductData(): array
    {
        $data = $this->validated();

        $mappings = [
            AccountProductType::ACCOUNT_CONTROL->name         => $data['savingsControlGLId'],
            AccountProductType::INTEREST_ON_ACCOUNT->name     => $data['interestOnSavingsGLId'],
            AccountProductType::INCOME_FROM_FEES->name        => $data['incomeFromFeeGLId'],
            AccountProductType::INCOME_FROM_PENALTIES->name   => $data['incomeFromPenaltyGLId'],
            AccountProductType::TRANSFERS_SUSPENSE->name      => $data['transfersInSuspenseGLId'],
        ];

        if (! empty($data['overdraftPortfolioGLId'])) {
            $mappings[AccountProductType::OVERDRAFT_PORTFOLIO_CONTROL->name] = $data['overdraftPortfolioGLId'];
        }

        if (! empty($data['writeOffGLId'])) {
            $mappings[AccountProductType::LOSSES_WRITTEN_OFF->name] = $data['writeOffGLId'];
        }

        if (! empty($data['escheatLiabilityGLId'])) {
            $mappings[AccountProductType::ESCHEAT_LIABILITY->name] = $data['escheatLiabilityGLId'];
        }

        if (! empty($data['incomeFromInterestGLId'])) {
            $mappings[AccountProductType::INCOME_FROM_INTEREST->name] = $data['incomeFromInterestGLId'];
        }

        $generalLedgers = collect($mappings)->map(fn ($glId, $typeName) => [
            'financial_account_type' => $typeName,
            'general_ledger_id'      => $glId,
        ])->values()->all();

        unset(
            $data['savingsControlGLId'],
            $data['interestOnSavingsGLId'],
            $data['incomeFromFeeGLId'],
            $data['incomeFromPenaltyGLId'],
            $data['transfersInSuspenseGLId'],
            $data['overdraftPortfolioGLId'],
            $data['writeOffGLId'],
            $data['escheatLiabilityGLId'],
            $data['incomeFromInterestGLId'],
        );

        $data['general_ledgers'] = $generalLedgers;

        return $data;
    }
}
