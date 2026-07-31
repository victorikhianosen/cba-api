<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\InvestmentProduct;

use App\Enums\Product\AccountProductType;
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
            'short_name'    => $this->short_name,
            'icon'          => $this->icon,
            'code'          => $this->code,
            'description'   => $this->description,
            'currency_id'   => $this->currency_id,
            'currency_code' => $this->currency_code,
            'interest_rate' => $this->interest_rate,
            'status'        => $this->status,
            'is_default'    => $this->is_default,

            'interest_posting_period' => $this->interest_posting_period,

            'tenure'              => $this->tenure,
            'tenure_unit'         => $this->tenure_unit,
            'min_deposit_amount'  => $this->min_deposit_amount,
            'max_deposit_amount'  => $this->max_deposit_amount,

            'auto_renew'                     => $this->auto_renew,
            'allow_top_up'                   => $this->allow_top_up,
            'allow_partial_withdrawal'       => $this->allow_partial_withdrawal,
            'allow_early_liquidation'        => $this->allow_early_liquidation,
            'early_liquidation_lock_days'    => $this->early_liquidation_lock_days,
            'early_liquidation_penalty_rate' => $this->early_liquidation_penalty_rate,

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
