<?php

namespace App\Http\Requests\Admin\AccountProduct;

use App\Enums\ProductCategory;
use App\Enums\SavingsProductFinancialAccountType;
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
            'product_type'                    => ['required', 'string', Rule::in(ProductCategory::DEPOSIT->productTypes())],
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
            'start_date'                          => ['nullable', 'date'],
            'close_date'                           => ['nullable', 'date', 'after_or_equal:start_date'],

            // Accounting (cash-based) — Fineract SavingProductAccountingParams field names.
            'savingsControlAccountId'         => ['required', 'integer', 'exists:general_ledgers,id'],
            'interestOnSavingsAccountId'      => ['required', 'integer', 'exists:general_ledgers,id'],
            'incomeFromFeeAccountId'          => ['required', 'integer', 'exists:general_ledgers,id'],
            'incomeFromPenaltyAccountId'      => ['required', 'integer', 'exists:general_ledgers,id'],
            'transfersInSuspenseAccountId'    => ['required', 'integer', 'exists:general_ledgers,id'],
            'overdraftPortfolioControlId'     => ['required_if:allow_overdraft,true', 'nullable', 'integer', 'exists:general_ledgers,id'],
            'writeOffAccountId'               => ['nullable', 'integer', 'exists:general_ledgers,id'],
            'escheatLiabilityId'              => ['nullable', 'integer', 'exists:general_ledgers,id'],
            'incomeFromInterestId'            => ['nullable', 'integer', 'exists:general_ledgers,id'],
        ];
    }

    public function toAccountProductData(): array
    {
        $data = $this->validated();

        $mappings = [
            SavingsProductFinancialAccountType::SAVINGS_REFERENCE->name       => $data['savingsReferenceAccountId'],
            SavingsProductFinancialAccountType::SAVINGS_CONTROL->name         => $data['savingsControlAccountId'],
            SavingsProductFinancialAccountType::INTEREST_ON_SAVINGS->name     => $data['interestOnSavingsAccountId'],
            SavingsProductFinancialAccountType::INCOME_FROM_FEES->name        => $data['incomeFromFeeAccountId'],
            SavingsProductFinancialAccountType::INCOME_FROM_PENALTIES->name   => $data['incomeFromPenaltyAccountId'],
            SavingsProductFinancialAccountType::TRANSFERS_SUSPENSE->name      => $data['transfersInSuspenseAccountId'],
        ];

        if (! empty($data['overdraftPortfolioControlId'])) {
            $mappings[SavingsProductFinancialAccountType::OVERDRAFT_PORTFOLIO_CONTROL->name] = $data['overdraftPortfolioControlId'];
        }

        if (! empty($data['writeOffAccountId'])) {
            $mappings[SavingsProductFinancialAccountType::LOSSES_WRITTEN_OFF->name] = $data['writeOffAccountId'];
        }

        if (! empty($data['escheatLiabilityId'])) {
            $mappings[SavingsProductFinancialAccountType::ESCHEAT_LIABILITY->name] = $data['escheatLiabilityId'];
        }

        if (! empty($data['incomeFromInterestId'])) {
            $mappings[SavingsProductFinancialAccountType::INCOME_FROM_INTEREST->name] = $data['incomeFromInterestId'];
        }

        $generalLedgers = collect($mappings)->map(fn ($glId, $typeName) => [
            'financial_account_type' => $typeName,
            'general_ledger_id'      => $glId,
        ])->values()->all();

        unset(
            $data['savingsReferenceAccountId'],
            $data['savingsControlAccountId'],
            $data['interestOnSavingsAccountId'],
            $data['incomeFromFeeAccountId'],
            $data['incomeFromPenaltyAccountId'],
            $data['transfersInSuspenseAccountId'],
            $data['overdraftPortfolioControlId'],
            $data['writeOffAccountId'],
            $data['escheatLiabilityId'],
            $data['incomeFromInterestId'],
        );

        $data['general_ledgers'] = $generalLedgers;

        return $data;
    }
}
