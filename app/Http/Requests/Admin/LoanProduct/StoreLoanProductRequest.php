<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\LoanProduct;

use App\Enums\Product\LoanProductType;
use App\Rules\StrictInteger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Field names mirror Fineract's LoanProductAccountingParams for cash-based loan accounting.
class StoreLoanProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Deliberately NOT snake-casing the name here — unlike a GL's internal key, a
        // loan product's name is a human-facing display label ("Personal Loan Product"),
        // not an identifier, and should be stored exactly as typed.
        if ($this->filled('interest_calculation_days_in_year')) {
            $this->merge([
                'interest_calculation_days_in_year' => (string) $this->interest_calculation_days_in_year,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255', 'unique:loan_products,name'],
            'description'   => ['nullable', 'string'],
            'currency_id'   => ['required', 'integer', 'exists:currencies,id'],
            'interest_rate' => ['nullable', 'numeric', 'min:0'],

            // Loan terms — Fineract m_loan_product field names.
            'min_principal_amount'                 => ['nullable', 'integer', new StrictInteger, 'min:0'],
            'max_principal_amount'                 => ['nullable', 'integer', new StrictInteger, 'min:0', 'gte:min_principal_amount'],
            'default_principal_amount'             => ['nullable', 'integer', new StrictInteger, 'min:0'],
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
            'arrears_tolerance_amount'              => ['nullable', 'integer', new StrictInteger, 'min:0'],
            'overdue_days_for_npa'                 => ['nullable', 'integer', 'min:0'],
            'penalty_interest_rate'                => ['nullable', 'numeric', 'min:0'],
            'days_in_month_type'                   => ['nullable', Rule::in(['actual', 'thirty'])],
            'interest_calculation_days_in_year'    => ['nullable', Rule::in(['360', '365'])],

            // Order a repayment is applied across the 4 buckets — must contain each
            // exactly once. Defaults to penalty->fee->interest->principal in the service
            // when omitted, so this stays optional here.
            'payment_allocation_order'   => ['nullable', 'array', 'size:4'],
            'payment_allocation_order.*' => ['required_with:payment_allocation_order', 'distinct', Rule::in(['penalty', 'fee', 'interest', 'principal'])],

            // Accounting (cash-based) — Fineract LoanProductAccountingParams field names.
            'fundSourceAccountId'           => ['required', 'integer', 'exists:general_ledgers,id'],
            'loanPortfolioAccountId'        => ['required', 'integer', 'exists:general_ledgers,id'],
            'interestOnLoanAccountId'       => ['required', 'integer', 'exists:general_ledgers,id'],
            'incomeFromFeeAccountId'        => ['required', 'integer', 'exists:general_ledgers,id'],
            'incomeFromPenaltyAccountId'    => ['required', 'integer', 'exists:general_ledgers,id'],
            'writeOffAccountId'             => ['required', 'integer', 'exists:general_ledgers,id'],
            'transfersInSuspenseAccountId'  => ['required', 'integer', 'exists:general_ledgers,id'],
            'overpaymentLiabilityAccountId' => ['required', 'integer', 'exists:general_ledgers,id'],
            'interestReceivableAccountId'   => ['required', 'integer', 'exists:general_ledgers,id'],
            'suspendedInterestAccountId'    => ['required', 'integer', 'exists:general_ledgers,id'],
        ];
    }

    public function toLoanProductData(): array
    {
        $data = $this->validated();

        $data['general_ledgers'] = [
            ['financial_account_type' => LoanProductType::FUND_SOURCE->name, 'general_ledger_id' => $data['fundSourceAccountId']],
            ['financial_account_type' => LoanProductType::LOAN_PORTFOLIO->name, 'general_ledger_id' => $data['loanPortfolioAccountId']],
            ['financial_account_type' => LoanProductType::INTEREST_ON_LOANS->name, 'general_ledger_id' => $data['interestOnLoanAccountId']],
            ['financial_account_type' => LoanProductType::INCOME_FROM_FEES->name, 'general_ledger_id' => $data['incomeFromFeeAccountId']],
            ['financial_account_type' => LoanProductType::INCOME_FROM_PENALTIES->name, 'general_ledger_id' => $data['incomeFromPenaltyAccountId']],
            ['financial_account_type' => LoanProductType::LOSSES_WRITTEN_OFF->name, 'general_ledger_id' => $data['writeOffAccountId']],
            ['financial_account_type' => LoanProductType::TRANSFERS_SUSPENSE->name, 'general_ledger_id' => $data['transfersInSuspenseAccountId']],
            ['financial_account_type' => LoanProductType::OVERPAYMENT->name, 'general_ledger_id' => $data['overpaymentLiabilityAccountId']],
            ['financial_account_type' => LoanProductType::INTEREST_RECEIVABLE->name, 'general_ledger_id' => $data['interestReceivableAccountId']],
            ['financial_account_type' => LoanProductType::SUSPENDED_INTEREST->name, 'general_ledger_id' => $data['suspendedInterestAccountId']],
        ];

        unset(
            $data['fundSourceAccountId'],
            $data['loanPortfolioAccountId'],
            $data['interestOnLoanAccountId'],
            $data['incomeFromFeeAccountId'],
            $data['incomeFromPenaltyAccountId'],
            $data['writeOffAccountId'],
            $data['interestReceivableAccountId'],
            $data['suspendedInterestAccountId'],
            $data['transfersInSuspenseAccountId'],
            $data['overpaymentLiabilityAccountId'],
        );

        return $data;
    }
}
