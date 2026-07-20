<?php

namespace App\Http\Resources\Admin\AccountProduct;

use App\Enums\AccountProductType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $accountByType = $this->whenLoaded(
            'generalLedgerMappings',
            fn () => $this->generalLedgerMappings->keyBy('financial_account_type_name'),
            collect(),
        );

        return [
            'id'                                 => $this->id,
            'name'                                => $this->name,
            'code'                                => $this->code,
            'product_type'                        => $this->product_type,
            'description'                         => $this->description,
            'currency_id'                         => $this->currency_id,
            'currency_code'                       => $this->currency_code,
            'interest_rate'                       => $this->interest_rate,
            'interest_type'                       => $this->interest_type,
            'interest_compounding_period'          => $this->interest_compounding_period,
            'interest_posting_period'             => $this->interest_posting_period,
            'interest_calculation_days_in_year'     => $this->interest_calculation_days_in_year,
            'min_required_opening_balance'         => $this->min_required_opening_balance,
            'min_required_balance'                => $this->min_required_balance,
            'enforce_min_required_balance'          => $this->enforce_min_required_balance,
            'locking_period_frequency'             => $this->locking_period_frequency,
            'locking_period_frequency_type'        => $this->locking_period_frequency_type,
            'allow_overdraft'                      => $this->allow_overdraft,
            'overdraft_limit'                      => $this->overdraft_limit,
            'overdraft_interest_rate'             => $this->overdraft_interest_rate,
            'withhold_tax'                         => $this->withhold_tax,
            'is_lien_allowed'                      => $this->is_lien_allowed,
            'max_allowed_lien_limit'               => $this->max_allowed_lien_limit,
            'dormancy_period_days'                 => $this->dormancy_period_days,

            'withdrawal_fee_amount'               => $this->withdrawal_fee_amount,
            'withdrawal_fee_type'                 => $this->withdrawal_fee_type,
            'annual_fee_amount'                    => $this->annual_fee_amount,
            'annual_fee_on_month'                  => $this->annual_fee_on_month,
            'annual_fee_on_day'                    => $this->annual_fee_on_day,
            'min_balance_for_interest_calculation' => $this->min_balance_for_interest_calculation,

            'created_by'                          => $this->created_by,
            'approved_by'                         => $this->approved_by,
            'approved_at'                          => $this->approved_at,
            'status'                              => $this->status,

            'savingsControlGLId'           => $accountByType[AccountProductType::ACCOUNT_CONTROL->name]?->general_ledger_id ?? null,
            'savingsControlGLCode'         => $accountByType[AccountProductType::ACCOUNT_CONTROL->name]?->generalLedger?->gl_code ?? null,
            'interestOnSavingsGLId'        => $accountByType[AccountProductType::INTEREST_ON_ACCOUNT->name]?->general_ledger_id ?? null,
            'interestOnSavingsGLCode'      => $accountByType[AccountProductType::INTEREST_ON_ACCOUNT->name]?->generalLedger?->gl_code ?? null,
            'incomeFromFeeGLId'            => $accountByType[AccountProductType::INCOME_FROM_FEES->name]?->general_ledger_id ?? null,
            'incomeFromFeeGLCode'          => $accountByType[AccountProductType::INCOME_FROM_FEES->name]?->generalLedger?->gl_code ?? null,
            'incomeFromPenaltyGLId'        => $accountByType[AccountProductType::INCOME_FROM_PENALTIES->name]?->general_ledger_id ?? null,
            'incomeFromPenaltyGLCode'      => $accountByType[AccountProductType::INCOME_FROM_PENALTIES->name]?->generalLedger?->gl_code ?? null,
            'transfersInSuspenseGLId'      => $accountByType[AccountProductType::TRANSFERS_SUSPENSE->name]?->general_ledger_id ?? null,
            'transfersInSuspenseGLCode'    => $accountByType[AccountProductType::TRANSFERS_SUSPENSE->name]?->generalLedger?->gl_code ?? null,
            'overdraftPortfolioGLId'       => $accountByType[AccountProductType::OVERDRAFT_PORTFOLIO_CONTROL->name]?->general_ledger_id ?? null,
            'overdraftPortfolioGLCode'     => $accountByType[AccountProductType::OVERDRAFT_PORTFOLIO_CONTROL->name]?->generalLedger?->gl_code ?? null,
            'writeOffGLId'                 => $accountByType[AccountProductType::LOSSES_WRITTEN_OFF->name]?->general_ledger_id ?? null,
            'writeOffGLCode'               => $accountByType[AccountProductType::LOSSES_WRITTEN_OFF->name]?->generalLedger?->gl_code ?? null,
            'escheatLiabilityGLId'         => $accountByType[AccountProductType::ESCHEAT_LIABILITY->name]?->general_ledger_id ?? null,
            'escheatLiabilityGLCode'       => $accountByType[AccountProductType::ESCHEAT_LIABILITY->name]?->generalLedger?->gl_code ?? null,
            'incomeFromInterestGLId'       => $accountByType[AccountProductType::INCOME_FROM_INTEREST->name]?->general_ledger_id ?? null,
            'incomeFromInterestGLCode'     => $accountByType[AccountProductType::INCOME_FROM_INTEREST->name]?->generalLedger?->gl_code ?? null,

            'created_at'                           => $this->created_at,
            'updated_at'                           => $this->updated_at,
        ];
    }
}
