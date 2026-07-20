<?php

namespace App\Http\Resources\Admin\InvestmentProduct;

use App\Enums\AccountProductType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvestmentProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $accountByType = $this->whenLoaded(
            'generalLedgerMappings',
            fn () => $this->generalLedgerMappings->keyBy('financial_account_type_name'),
            collect(),
        );

        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'code'          => $this->code,
            'description'   => $this->description,
            'currency_id'   => $this->currency_id,
            'currency_code' => $this->currency_code,
            'interest_rate' => $this->interest_rate,
            'status'        => $this->status,

            'interest_type'                       => $this->interest_type,
            'interest_compounding_period'          => $this->interest_compounding_period,
            'interest_posting_period'              => $this->interest_posting_period,
            'interest_calculation_days_in_year'    => $this->interest_calculation_days_in_year,
            'min_required_opening_balance'         => $this->min_required_opening_balance,
            'min_required_balance'                 => $this->min_required_balance,
            'locking_period_frequency'             => $this->locking_period_frequency,
            'locking_period_frequency_type'        => $this->locking_period_frequency_type,

            'min_deposit_term'                     => $this->min_deposit_term,
            'max_deposit_term'                     => $this->max_deposit_term,
            'min_deposit_term_type'                 => $this->min_deposit_term_type,
            'max_deposit_term_type'                 => $this->max_deposit_term_type,
            'in_multiples_of_deposit_term'          => $this->in_multiples_of_deposit_term,
            'in_multiples_of_deposit_term_type'     => $this->in_multiples_of_deposit_term_type,
            'min_deposit_amount'                    => $this->min_deposit_amount,
            'max_deposit_amount'                    => $this->max_deposit_amount,
            'pre_closure_penal_applicable'          => $this->pre_closure_penal_applicable,
            'pre_closure_penal_interest'            => $this->pre_closure_penal_interest,
            'pre_closure_penal_interest_on_type'    => $this->pre_closure_penal_interest_on_type,

            'savingsControlAccountId'      => $accountByType[AccountProductType::ACCOUNT_CONTROL->name]?->general_ledger_id ?? null,
            'savingsControlAccountCode'    => $accountByType[AccountProductType::ACCOUNT_CONTROL->name]?->generalLedger?->gl_code ?? null,
            'interestOnSavingsAccountId'   => $accountByType[AccountProductType::INTEREST_ON_ACCOUNT->name]?->general_ledger_id ?? null,
            'interestOnSavingsAccountCode' => $accountByType[AccountProductType::INTEREST_ON_ACCOUNT->name]?->generalLedger?->gl_code ?? null,
            'incomeFromFeeAccountId'       => $accountByType[AccountProductType::INCOME_FROM_FEES->name]?->general_ledger_id ?? null,
            'incomeFromFeeAccountCode'     => $accountByType[AccountProductType::INCOME_FROM_FEES->name]?->generalLedger?->gl_code ?? null,
            'incomeFromPenaltyAccountId'   => $accountByType[AccountProductType::INCOME_FROM_PENALTIES->name]?->general_ledger_id ?? null,
            'incomeFromPenaltyAccountCode' => $accountByType[AccountProductType::INCOME_FROM_PENALTIES->name]?->generalLedger?->gl_code ?? null,
            'transfersInSuspenseAccountId' => $accountByType[AccountProductType::TRANSFERS_SUSPENSE->name]?->general_ledger_id ?? null,
            'transfersInSuspenseAccountCode' => $accountByType[AccountProductType::TRANSFERS_SUSPENSE->name]?->generalLedger?->gl_code ?? null,
            'writeOffAccountId'            => $accountByType[AccountProductType::LOSSES_WRITTEN_OFF->name]?->general_ledger_id ?? null,
            'writeOffAccountCode'          => $accountByType[AccountProductType::LOSSES_WRITTEN_OFF->name]?->generalLedger?->gl_code ?? null,
            'escheatLiabilityId'           => $accountByType[AccountProductType::ESCHEAT_LIABILITY->name]?->general_ledger_id ?? null,
            'escheatLiabilityCode'         => $accountByType[AccountProductType::ESCHEAT_LIABILITY->name]?->generalLedger?->gl_code ?? null,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
