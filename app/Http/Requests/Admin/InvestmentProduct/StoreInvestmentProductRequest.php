<?php

namespace App\Http\Requests\Admin\InvestmentProduct;

use App\Enums\SavingsProductFinancialAccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Field names mirror Fineract's SavingProductAccountingParams
 * (fineract-core/.../accounting/common/AccountingConstants.java) — Fixed
 * Deposit Product accounting in Fineract reuses the savings product's GL
 * vocabulary rather than defining its own.
 */
class StoreInvestmentProductRequest extends FormRequest
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
            'name'          => ['required', 'string', 'max:255', 'unique:investment_products,name'],
            'description'   => ['nullable', 'string'],
            'currency_id'   => ['required', 'integer', 'exists:currencies,id'],
            'interest_rate' => ['nullable', 'numeric', 'min:0'],
            'interest_type'                   => ['nullable', Rule::in(['flat', 'daily_balance', 'average_daily_balance', 'tiered'])],
            'interest_compounding_period'     => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'annually'])],
            'interest_posting_period'         => ['nullable', Rule::in(['daily', 'monthly', 'quarterly', 'bi_annually', 'annually'])],
            'interest_calculation_days_in_year' => ['nullable', Rule::in(['360', '365'])],
            'min_required_opening_balance'    => ['nullable', 'numeric', 'min:0'],
            'min_required_balance'            => ['nullable', 'numeric', 'min:0'],
            'locking_period_frequency'        => ['nullable', 'integer', 'min:1'],
            'locking_period_frequency_type'   => ['nullable', Rule::in(['days', 'weeks', 'months', 'years'])],

            // Fixed deposit terms — Fineract m_deposit_product_term_and_preclosure field names.
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

            // Accounting (cash-based) — Fineract SavingProductAccountingParams field names.
            'savingsReferenceAccountId'    => ['required', 'integer', 'exists:general_ledgers,id'],
            'savingsControlAccountId'      => ['required', 'integer', 'exists:general_ledgers,id'],
            'interestOnSavingsAccountId'   => ['required', 'integer', 'exists:general_ledgers,id'],
            'incomeFromFeeAccountId'       => ['required', 'integer', 'exists:general_ledgers,id'],
            'incomeFromPenaltyAccountId'   => ['required', 'integer', 'exists:general_ledgers,id'],
            'transfersInSuspenseAccountId' => ['required', 'integer', 'exists:general_ledgers,id'],
            'writeOffAccountId'            => ['nullable', 'integer', 'exists:general_ledgers,id'],
            'escheatLiabilityId'           => ['nullable', 'integer', 'exists:general_ledgers,id'],
        ];
    }

    public function toInvestmentProductData(): array
    {
        $data = $this->validated();

        $mappings = [
            SavingsProductFinancialAccountType::SAVINGS_REFERENCE->name     => $data['savingsReferenceAccountId'],
            SavingsProductFinancialAccountType::SAVINGS_CONTROL->name       => $data['savingsControlAccountId'],
            SavingsProductFinancialAccountType::INTEREST_ON_SAVINGS->name   => $data['interestOnSavingsAccountId'],
            SavingsProductFinancialAccountType::INCOME_FROM_FEES->name      => $data['incomeFromFeeAccountId'],
            SavingsProductFinancialAccountType::INCOME_FROM_PENALTIES->name => $data['incomeFromPenaltyAccountId'],
            SavingsProductFinancialAccountType::TRANSFERS_SUSPENSE->name    => $data['transfersInSuspenseAccountId'],
        ];

        if (! empty($data['writeOffAccountId'])) {
            $mappings[SavingsProductFinancialAccountType::LOSSES_WRITTEN_OFF->name] = $data['writeOffAccountId'];
        }

        if (! empty($data['escheatLiabilityId'])) {
            $mappings[SavingsProductFinancialAccountType::ESCHEAT_LIABILITY->name] = $data['escheatLiabilityId'];
        }

        $data['general_ledgers'] = collect($mappings)->map(fn ($glId, $typeName) => [
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
            $data['writeOffAccountId'],
            $data['escheatLiabilityId'],
        );

        return $data;
    }
}
