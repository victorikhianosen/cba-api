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

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255', 'unique:loan_products,name'],
            'description'   => ['nullable', 'string'],
            'currency_id'   => ['required', 'integer', 'exists:currencies,id'],
            'interest_rate' => ['nullable', 'numeric', 'min:0'],
            'management_fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'processing_fee_percentage'  => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Loan terms — Fineract m_loan_product field names.
            'min_principal_amount'                 => ['nullable', 'integer', new StrictInteger, 'min:0'],
            'max_principal_amount'                 => ['nullable', 'integer', new StrictInteger, 'min:0', 'gte:min_principal_amount'],
            'interest_rate_frequency_type'          => ['nullable', Rule::in(['per_day', 'per_week', 'per_month', 'per_year'])],
            'interest_method'                      => ['nullable', Rule::in(['declining_balance', 'flat'])],
            'repayment_every'                      => ['nullable', 'integer', 'min:1'],
            'repayment_frequency_type'              => ['nullable', Rule::in(['days', 'weeks', 'months'])],
            'arrears_tolerance_amount'              => ['nullable', 'integer', new StrictInteger, 'min:0'],
            'penalty_interest_rate'                => ['nullable', 'numeric', 'min:0'],

            // Order a repayment is applied across the 4 buckets — must contain each
            // exactly once. Defaults to penalty->fee->interest->principal in the service
            // when omitted, so this stays optional here.
            'payment_allocation_order'   => ['nullable', 'array', 'size:4'],
            'payment_allocation_order.*' => ['required_with:payment_allocation_order', 'distinct', Rule::in(['penalty', 'fee', 'interest', 'principal'])],

            // Accounting (cash-based) — Fineract LoanProductAccountingParams field names.
            'loanPortfolioAccountId'        => ['required', 'integer', 'exists:general_ledgers,id'],
            'interestOnLoanAccountId'       => ['required', 'integer', 'exists:general_ledgers,id'],
            'incomeFromFeeAccountId'        => ['required', 'integer', 'exists:general_ledgers,id'],
            'incomeFromPenaltyAccountId'    => ['required', 'integer', 'exists:general_ledgers,id'],
            'writeOffAccountId'             => ['required', 'integer', 'exists:general_ledgers,id'],
            'overpaymentLiabilityAccountId' => ['required', 'integer', 'exists:general_ledgers,id'],
            'interestReceivableAccountId'   => ['required', 'integer', 'exists:general_ledgers,id'],
            'suspendedInterestAccountId'    => ['required', 'integer', 'exists:general_ledgers,id'],
            'recoveryIncomeAccountId'       => ['required', 'integer', 'exists:general_ledgers,id'],
            'managementFeeIncomeAccountId'   => ['required', 'integer', 'exists:general_ledgers,id'],
            'processingFeeIncomeAccountId'   => ['required', 'integer', 'exists:general_ledgers,id'],
        ];
    }

    public function toLoanProductData(): array
    {
        $data = $this->validated();

        $data['general_ledgers'] = [
            ['financial_account_type' => LoanProductType::LOAN_PORTFOLIO->name, 'general_ledger_id' => $data['loanPortfolioAccountId']],
            ['financial_account_type' => LoanProductType::INTEREST_ON_LOANS->name, 'general_ledger_id' => $data['interestOnLoanAccountId']],
            ['financial_account_type' => LoanProductType::INCOME_FROM_FEES->name, 'general_ledger_id' => $data['incomeFromFeeAccountId']],
            ['financial_account_type' => LoanProductType::INCOME_FROM_PENALTIES->name, 'general_ledger_id' => $data['incomeFromPenaltyAccountId']],
            ['financial_account_type' => LoanProductType::LOSSES_WRITTEN_OFF->name, 'general_ledger_id' => $data['writeOffAccountId']],
            ['financial_account_type' => LoanProductType::OVERPAYMENT->name, 'general_ledger_id' => $data['overpaymentLiabilityAccountId']],
            ['financial_account_type' => LoanProductType::INTEREST_RECEIVABLE->name, 'general_ledger_id' => $data['interestReceivableAccountId']],
            ['financial_account_type' => LoanProductType::SUSPENDED_INTEREST->name, 'general_ledger_id' => $data['suspendedInterestAccountId']],
            ['financial_account_type' => LoanProductType::RECOVERY_INCOME->name, 'general_ledger_id' => $data['recoveryIncomeAccountId']],
            ['financial_account_type' => LoanProductType::MANAGEMENT_FEE_INCOME->name, 'general_ledger_id' => $data['managementFeeIncomeAccountId']],
            ['financial_account_type' => LoanProductType::PROCESSING_FEE_INCOME->name, 'general_ledger_id' => $data['processingFeeIncomeAccountId']],
        ];

        unset(
            $data['loanPortfolioAccountId'],
            $data['interestOnLoanAccountId'],
            $data['incomeFromFeeAccountId'],
            $data['incomeFromPenaltyAccountId'],
            $data['writeOffAccountId'],
            $data['interestReceivableAccountId'],
            $data['suspendedInterestAccountId'],
            $data['overpaymentLiabilityAccountId'],
            $data['recoveryIncomeAccountId'],
            $data['managementFeeIncomeAccountId'],
            $data['processingFeeIncomeAccountId'],
        );

        return $data;
    }
}
