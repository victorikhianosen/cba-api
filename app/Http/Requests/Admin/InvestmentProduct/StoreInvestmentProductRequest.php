<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\InvestmentProduct;

use App\Enums\Product\InvestmentProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'short_name'    => ['nullable', 'string', 'max:45'],
            'icon'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'description'   => ['nullable', 'string'],
            'currency_id'   => ['required', 'integer', 'exists:currencies,id'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'regex:/^\d+(\.\d{1,2})?$/'],

            'allowed_interest_posting_periods'   => ['nullable', 'array'],
            'allowed_interest_posting_periods.*' => [Rule::in(['upfront', 'daily', 'weekly', 'monthly', 'quarterly', 'bi_annually', 'annually', 'bullet'])],

            'min_tenure_days'       => ['nullable', 'integer', 'min:1', 'max:365'],
            'max_tenure_days'       => ['nullable', 'integer', 'min:1', 'max:365', 'gte:min_tenure_days'],
            'min_deposit_amount'    => ['nullable', 'numeric', 'min:0'],
            'max_deposit_amount'    => ['nullable', 'numeric', 'min:0', 'gte:min_deposit_amount'],

            // Whether auto-renewal is offered at all — the customer picks simple vs compound
            // rollover for their own investment when they open it (see StoreInvestmentRequest).
            'auto_renew'                    => ['nullable', 'boolean'],
            'allow_top_up'                  => ['nullable', 'boolean'],
            'allow_partial_withdrawal'      => ['nullable', 'boolean'],
            'minimum_retention_percentage'  => ['nullable', 'numeric', 'min:0', 'max:100', 'regex:/^\d+(\.\d{1,2})?$/'],
            'allow_early_liquidation'       => ['nullable', 'boolean'],
            'early_liquidation_lock_days'   => ['nullable', 'integer', 'min:0'],
            'early_liquidation_penalty_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'regex:/^\d+(\.\d{1,2})?$/'],
            'withholding_tax_rate'          => ['nullable', 'numeric', 'min:0', 'max:100', 'regex:/^\d+(\.\d{1,2})?$/'],
            'is_default'                    => ['nullable', 'boolean'],

            // Accounting (cash-based) — see App\Enums\Product\InvestmentProductType for the role each maps to.
            'investmentFundingAccountId'             => ['required', 'integer', 'exists:general_ledgers,id'],
            'interestExpenseAccountId'               => ['required', 'integer', 'exists:general_ledgers,id'],
            'interestPayableAccountId'               => ['required', 'integer', 'exists:general_ledgers,id'],
            'earlyLiquidationPenaltyIncomeAccountId' => ['required', 'integer', 'exists:general_ledgers,id'],
            'withholdingTaxPayableAccountId'         => ['required', 'integer', 'exists:general_ledgers,id'],
        ];
    }

    public function toInvestmentProductData(): array
    {
        $data = $this->validated();

        // Uploaded separately via InvestmentProductService::create()'s $icon param — never
        // passed through to Model::create() as a raw UploadedFile.
        unset($data['icon']);

        $mappings = [
            InvestmentProductType::INVESTMENT_FUNDING->name => $data['investmentFundingAccountId'],
            InvestmentProductType::INTEREST_EXPENSE->name => $data['interestExpenseAccountId'],
            InvestmentProductType::INTEREST_PAYABLE->name => $data['interestPayableAccountId'],
            InvestmentProductType::EARLY_LIQUIDATION_PENALTY_INCOME->name => $data['earlyLiquidationPenaltyIncomeAccountId'],
            InvestmentProductType::WITHHOLDING_TAX_PAYABLE->name => $data['withholdingTaxPayableAccountId'],
        ];

        $data['general_ledgers'] = collect($mappings)->map(fn ($glId, $typeName) => [
            'financial_account_type' => $typeName,
            'general_ledger_id'      => $glId,
        ])->values()->all();

        unset(
            $data['investmentFundingAccountId'],
            $data['interestExpenseAccountId'],
            $data['interestPayableAccountId'],
            $data['earlyLiquidationPenaltyIncomeAccountId'],
            $data['withholdingTaxPayableAccountId'],
        );

        return $data;
    }
}
