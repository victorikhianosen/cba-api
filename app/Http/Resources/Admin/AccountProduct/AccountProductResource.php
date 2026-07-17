<?php

namespace App\Http\Resources\Admin\AccountProduct;

use App\Enums\SavingsProductFinancialAccountType;
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
            'currency_digits'                     => $this->currency_digits,
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
            'start_date'                          => $this->start_date,
            'close_date'                           => $this->close_date,

            'created_by'                          => $this->created_by,
            'approved_by'                         => $this->approved_by,
            'approved_at'                          => $this->approved_at,
            'status'                              => $this->status,

            'savingsReferenceAccountId'    => $accountByType[SavingsProductFinancialAccountType::SAVINGS_REFERENCE->name]?->general_ledger_id ?? null,
            'savingsReferenceAccountCode'  => $accountByType[SavingsProductFinancialAccountType::SAVINGS_REFERENCE->name]?->generalLedger?->gl_code ?? null,
            'savingsControlAccountId'      => $accountByType[SavingsProductFinancialAccountType::SAVINGS_CONTROL->name]?->general_ledger_id ?? null,
            'savingsControlAccountCode'    => $accountByType[SavingsProductFinancialAccountType::SAVINGS_CONTROL->name]?->generalLedger?->gl_code ?? null,
            'interestOnSavingsAccountId'   => $accountByType[SavingsProductFinancialAccountType::INTEREST_ON_SAVINGS->name]?->general_ledger_id ?? null,
            'interestOnSavingsAccountCode' => $accountByType[SavingsProductFinancialAccountType::INTEREST_ON_SAVINGS->name]?->generalLedger?->gl_code ?? null,
            'incomeFromFeeAccountId'       => $accountByType[SavingsProductFinancialAccountType::INCOME_FROM_FEES->name]?->general_ledger_id ?? null,
            'incomeFromFeeAccountCode'     => $accountByType[SavingsProductFinancialAccountType::INCOME_FROM_FEES->name]?->generalLedger?->gl_code ?? null,
            'incomeFromPenaltyAccountId'   => $accountByType[SavingsProductFinancialAccountType::INCOME_FROM_PENALTIES->name]?->general_ledger_id ?? null,
            'incomeFromPenaltyAccountCode' => $accountByType[SavingsProductFinancialAccountType::INCOME_FROM_PENALTIES->name]?->generalLedger?->gl_code ?? null,
            'transfersInSuspenseAccountId' => $accountByType[SavingsProductFinancialAccountType::TRANSFERS_SUSPENSE->name]?->general_ledger_id ?? null,
            'transfersInSuspenseAccountCode' => $accountByType[SavingsProductFinancialAccountType::TRANSFERS_SUSPENSE->name]?->generalLedger?->gl_code ?? null,
            'overdraftPortfolioControlId'  => $accountByType[SavingsProductFinancialAccountType::OVERDRAFT_PORTFOLIO_CONTROL->name]?->general_ledger_id ?? null,
            'overdraftPortfolioControlCode' => $accountByType[SavingsProductFinancialAccountType::OVERDRAFT_PORTFOLIO_CONTROL->name]?->generalLedger?->gl_code ?? null,
            'writeOffAccountId'            => $accountByType[SavingsProductFinancialAccountType::LOSSES_WRITTEN_OFF->name]?->general_ledger_id ?? null,
            'writeOffAccountCode'          => $accountByType[SavingsProductFinancialAccountType::LOSSES_WRITTEN_OFF->name]?->generalLedger?->gl_code ?? null,
            'escheatLiabilityId'           => $accountByType[SavingsProductFinancialAccountType::ESCHEAT_LIABILITY->name]?->general_ledger_id ?? null,
            'escheatLiabilityCode'         => $accountByType[SavingsProductFinancialAccountType::ESCHEAT_LIABILITY->name]?->generalLedger?->gl_code ?? null,
            'incomeFromInterestId'         => $accountByType[SavingsProductFinancialAccountType::INCOME_FROM_INTEREST->name]?->general_ledger_id ?? null,
            'incomeFromInterestCode'       => $accountByType[SavingsProductFinancialAccountType::INCOME_FROM_INTEREST->name]?->generalLedger?->gl_code ?? null,

            'created_at'                           => $this->created_at,
            'updated_at'                           => $this->updated_at,
        ];
    }
}
