<?php

namespace App\Http\Resources\Admin\LoanProduct;

use App\Enums\LoanProductFinancialAccountType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $accountByType = $this->whenLoaded(
            'generalLedgerMappings',
            fn () => $this->generalLedgerMappings->keyBy('financial_account_type_name'),
            collect(),
        );

        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'code'              => $this->code,
            'description'       => $this->description,
            'currency_id'       => $this->currency_id,
            'currency_code'     => $this->currency_code,
            'interest_rate'     => $this->interest_rate,
            'status'            => $this->status,

            'min_principal_amount'                 => $this->min_principal_amount,
            'max_principal_amount'                 => $this->max_principal_amount,
            'default_principal_amount'             => $this->default_principal_amount,
            'min_nominal_interest_rate_per_period' => $this->min_nominal_interest_rate_per_period,
            'max_nominal_interest_rate_per_period' => $this->max_nominal_interest_rate_per_period,
            'interest_rate_frequency_type'          => $this->interest_rate_frequency_type,
            'interest_method'                      => $this->interest_method,
            'interest_calculation_period_type'      => $this->interest_calculation_period_type,
            'repayment_every'                      => $this->repayment_every,
            'repayment_frequency_type'              => $this->repayment_frequency_type,
            'number_of_repayments'                 => $this->number_of_repayments,
            'min_number_of_repayments'              => $this->min_number_of_repayments,
            'max_number_of_repayments'              => $this->max_number_of_repayments,
            'amortization_method'                  => $this->amortization_method,
            'grace_on_principal_periods'            => $this->grace_on_principal_periods,
            'grace_on_interest_periods'             => $this->grace_on_interest_periods,
            'grace_interest_free_periods'           => $this->grace_interest_free_periods,
            'grace_on_arrears_ageing'               => $this->grace_on_arrears_ageing,
            'arrears_tolerance_amount'              => $this->arrears_tolerance_amount,
            'overdue_days_for_npa'                 => $this->overdue_days_for_npa,
            'days_in_month_type'                   => $this->days_in_month_type,
            'interest_calculation_days_in_year'    => $this->interest_calculation_days_in_year,

            'fundSourceAccountId'           => $accountByType[LoanProductFinancialAccountType::FUND_SOURCE->name]?->general_ledger_id ?? null,
            'fundSourceAccountCode'         => $accountByType[LoanProductFinancialAccountType::FUND_SOURCE->name]?->generalLedger?->gl_code ?? null,
            'loanPortfolioAccountId'        => $accountByType[LoanProductFinancialAccountType::LOAN_PORTFOLIO->name]?->general_ledger_id ?? null,
            'loanPortfolioAccountCode'      => $accountByType[LoanProductFinancialAccountType::LOAN_PORTFOLIO->name]?->generalLedger?->gl_code ?? null,
            'interestOnLoanAccountId'       => $accountByType[LoanProductFinancialAccountType::INTEREST_ON_LOANS->name]?->general_ledger_id ?? null,
            'interestOnLoanAccountCode'     => $accountByType[LoanProductFinancialAccountType::INTEREST_ON_LOANS->name]?->generalLedger?->gl_code ?? null,
            'incomeFromFeeAccountId'        => $accountByType[LoanProductFinancialAccountType::INCOME_FROM_FEES->name]?->general_ledger_id ?? null,
            'incomeFromFeeAccountCode'      => $accountByType[LoanProductFinancialAccountType::INCOME_FROM_FEES->name]?->generalLedger?->gl_code ?? null,
            'incomeFromPenaltyAccountId'    => $accountByType[LoanProductFinancialAccountType::INCOME_FROM_PENALTIES->name]?->general_ledger_id ?? null,
            'incomeFromPenaltyAccountCode'  => $accountByType[LoanProductFinancialAccountType::INCOME_FROM_PENALTIES->name]?->generalLedger?->gl_code ?? null,
            'writeOffAccountId'             => $accountByType[LoanProductFinancialAccountType::LOSSES_WRITTEN_OFF->name]?->general_ledger_id ?? null,
            'writeOffAccountCode'           => $accountByType[LoanProductFinancialAccountType::LOSSES_WRITTEN_OFF->name]?->generalLedger?->gl_code ?? null,
            'transfersInSuspenseAccountId'  => $accountByType[LoanProductFinancialAccountType::TRANSFERS_SUSPENSE->name]?->general_ledger_id ?? null,
            'transfersInSuspenseAccountCode' => $accountByType[LoanProductFinancialAccountType::TRANSFERS_SUSPENSE->name]?->generalLedger?->gl_code ?? null,
            'overpaymentLiabilityAccountId' => $accountByType[LoanProductFinancialAccountType::OVERPAYMENT->name]?->general_ledger_id ?? null,
            'overpaymentLiabilityAccountCode' => $accountByType[LoanProductFinancialAccountType::OVERPAYMENT->name]?->generalLedger?->gl_code ?? null,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
