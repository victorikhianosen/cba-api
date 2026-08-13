<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\LoanProduct;

use App\Enums\Product\LoanProductType;
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
            'management_fee_percentage' => $this->management_fee_percentage,
            'processing_fee_percentage'  => $this->processing_fee_percentage,
            'status'            => $this->status,

            'min_principal_amount'                 => $this->min_principal_amount,
            'max_principal_amount'                 => $this->max_principal_amount,
            'interest_rate_frequency_type'          => $this->interest_rate_frequency_type,
            'interest_method'                      => $this->interest_method,
            'repayment_every'                      => $this->repayment_every,
            'repayment_frequency_type'              => $this->repayment_frequency_type,
            'arrears_tolerance_amount'              => $this->arrears_tolerance_amount,
            'penalty_interest_rate'                => $this->penalty_interest_rate,
            'payment_allocation_order'              => $this->payment_allocation_order,

            'loanPortfolioAccountId'        => $accountByType[LoanProductType::LOAN_PORTFOLIO->name]?->general_ledger_id ?? null,
            'loanPortfolioAccountCode'      => $accountByType[LoanProductType::LOAN_PORTFOLIO->name]?->generalLedger?->gl_code ?? null,
            'interestOnLoanAccountId'       => $accountByType[LoanProductType::INTEREST_ON_LOANS->name]?->general_ledger_id ?? null,
            'interestOnLoanAccountCode'     => $accountByType[LoanProductType::INTEREST_ON_LOANS->name]?->generalLedger?->gl_code ?? null,
            'incomeFromFeeAccountId'        => $accountByType[LoanProductType::INCOME_FROM_FEES->name]?->general_ledger_id ?? null,
            'incomeFromFeeAccountCode'      => $accountByType[LoanProductType::INCOME_FROM_FEES->name]?->generalLedger?->gl_code ?? null,
            'incomeFromPenaltyAccountId'    => $accountByType[LoanProductType::INCOME_FROM_PENALTIES->name]?->general_ledger_id ?? null,
            'incomeFromPenaltyAccountCode'  => $accountByType[LoanProductType::INCOME_FROM_PENALTIES->name]?->generalLedger?->gl_code ?? null,
            'writeOffAccountId'             => $accountByType[LoanProductType::LOSSES_WRITTEN_OFF->name]?->general_ledger_id ?? null,
            'writeOffAccountCode'           => $accountByType[LoanProductType::LOSSES_WRITTEN_OFF->name]?->generalLedger?->gl_code ?? null,
            'overpaymentLiabilityAccountId' => $accountByType[LoanProductType::OVERPAYMENT->name]?->general_ledger_id ?? null,
            'overpaymentLiabilityAccountCode' => $accountByType[LoanProductType::OVERPAYMENT->name]?->generalLedger?->gl_code ?? null,
            'interestReceivableAccountId'   => $accountByType[LoanProductType::INTEREST_RECEIVABLE->name]?->general_ledger_id ?? null,
            'interestReceivableAccountCode' => $accountByType[LoanProductType::INTEREST_RECEIVABLE->name]?->generalLedger?->gl_code ?? null,
            'suspendedInterestAccountId'    => $accountByType[LoanProductType::SUSPENDED_INTEREST->name]?->general_ledger_id ?? null,
            'suspendedInterestAccountCode'  => $accountByType[LoanProductType::SUSPENDED_INTEREST->name]?->generalLedger?->gl_code ?? null,
            'recoveryIncomeAccountId'       => $accountByType[LoanProductType::RECOVERY_INCOME->name]?->general_ledger_id ?? null,
            'recoveryIncomeAccountCode'     => $accountByType[LoanProductType::RECOVERY_INCOME->name]?->generalLedger?->gl_code ?? null,
            'managementFeeIncomeAccountId'   => $accountByType[LoanProductType::MANAGEMENT_FEE_INCOME->name]?->general_ledger_id ?? null,
            'managementFeeIncomeAccountCode' => $accountByType[LoanProductType::MANAGEMENT_FEE_INCOME->name]?->generalLedger?->gl_code ?? null,
            'processingFeeIncomeAccountId'   => $accountByType[LoanProductType::PROCESSING_FEE_INCOME->name]?->general_ledger_id ?? null,
            'processingFeeIncomeAccountCode' => $accountByType[LoanProductType::PROCESSING_FEE_INCOME->name]?->generalLedger?->gl_code ?? null,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
