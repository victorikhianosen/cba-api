<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\InvestmentProduct;

use App\Enums\Product\InvestmentProductType;
use App\Services\Storage\FileStorageService;
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
            'icon'          => app(FileStorageService::class)->url($this->icon),
            'code'          => $this->code,
            'description'   => $this->description,
            'currency_id'   => $this->currency_id,
            'currency_code' => $this->currency_code,
            'interest_rate' => $this->interest_rate,
            'status'        => $this->status,
            'is_default'    => $this->is_default,

            'allowed_interest_posting_periods' => $this->allowed_interest_posting_periods,

            'min_tenure_days'     => $this->min_tenure_days,
            'max_tenure_days'     => $this->max_tenure_days,
            'min_deposit_amount'  => $this->min_deposit_amount,
            'max_deposit_amount'  => $this->max_deposit_amount,

            'auto_renew'                     => $this->auto_renew,
            'allow_top_up'                   => $this->allow_top_up,
            'allow_partial_withdrawal'       => $this->allow_partial_withdrawal,
            'allow_early_liquidation'        => $this->allow_early_liquidation,
            'early_liquidation_lock_days'    => $this->early_liquidation_lock_days,
            'early_liquidation_penalty_rate' => $this->early_liquidation_penalty_rate,
            'withholding_tax_rate'           => $this->withholding_tax_rate,

            'investmentFundingAccountId'        => $accountByType[InvestmentProductType::INVESTMENT_FUNDING->name]?->general_ledger_id ?? null,
            'investmentFundingAccountCode'      => $accountByType[InvestmentProductType::INVESTMENT_FUNDING->name]?->generalLedger?->gl_code ?? null,
            'interestExpenseAccountId'          => $accountByType[InvestmentProductType::INTEREST_EXPENSE->name]?->general_ledger_id ?? null,
            'interestExpenseAccountCode'        => $accountByType[InvestmentProductType::INTEREST_EXPENSE->name]?->generalLedger?->gl_code ?? null,
            'interestPayableAccountId'          => $accountByType[InvestmentProductType::INTEREST_PAYABLE->name]?->general_ledger_id ?? null,
            'interestPayableAccountCode'        => $accountByType[InvestmentProductType::INTEREST_PAYABLE->name]?->generalLedger?->gl_code ?? null,
            'earlyLiquidationPenaltyIncomeAccountId'   => $accountByType[InvestmentProductType::EARLY_LIQUIDATION_PENALTY_INCOME->name]?->general_ledger_id ?? null,
            'earlyLiquidationPenaltyIncomeAccountCode' => $accountByType[InvestmentProductType::EARLY_LIQUIDATION_PENALTY_INCOME->name]?->generalLedger?->gl_code ?? null,
            'withholdingTaxPayableAccountId'    => $accountByType[InvestmentProductType::WITHHOLDING_TAX_PAYABLE->name]?->general_ledger_id ?? null,
            'withholdingTaxPayableAccountCode'  => $accountByType[InvestmentProductType::WITHHOLDING_TAX_PAYABLE->name]?->generalLedger?->gl_code ?? null,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
