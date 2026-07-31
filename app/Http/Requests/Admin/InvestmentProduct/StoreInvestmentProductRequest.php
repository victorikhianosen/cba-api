<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\InvestmentProduct;

use App\Enums\Product\AccountProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Field names mirror Fineract's SavingProductAccountingParams — Fixed Deposit Product accounting reuses the savings product's GL vocabulary.
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
            'icon'          => ['nullable', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'currency_id'   => ['required', 'integer', 'exists:currencies,id'],
            'interest_rate' => ['nullable', 'numeric', 'min:0'],
            'interest_posting_period' => ['nullable', Rule::in(['upfront', 'daily', 'weekly', 'monthly', 'quarterly', 'bi_annually', 'annually', 'bullet'])],

            'tenure'                => ['nullable', 'integer', 'min:1'],
            'tenure_unit'           => ['nullable', Rule::in(['days', 'weeks', 'months', 'years'])],
            'min_deposit_amount'    => ['nullable', 'numeric', 'min:0'],
            'max_deposit_amount'    => ['nullable', 'numeric', 'min:0', 'gte:min_deposit_amount'],

            'auto_renew'                    => ['nullable', 'boolean'],
            'allow_top_up'                  => ['nullable', 'boolean'],
            'allow_partial_withdrawal'      => ['nullable', 'boolean'],
            'allow_early_liquidation'       => ['nullable', 'boolean'],
            'early_liquidation_lock_days'   => ['nullable', 'integer', 'min:0'],
            'early_liquidation_penalty_rate' => ['nullable', 'numeric', 'min:0'],
            'is_default'                    => ['nullable', 'boolean'],

            // Accounting (cash-based) — Fineract SavingProductAccountingParams field names.
            'savingsControlAccountId'      => ['required', 'integer', 'exists:general_ledgers,id'],
            'interestOnSavingsAccountId'   => ['required', 'integer', 'exists:general_ledgers,id'],
            'incomeFromFeeAccountId'       => ['required', 'integer', 'exists:general_ledgers,id'],
            'incomeFromPenaltyAccountId'   => ['required', 'integer', 'exists:general_ledgers,id'],
            'transfersInSuspenseAccountId' => ['required', 'integer', 'exists:general_ledgers,id'],
            'writeOffAccountId'            => ['nullable', 'integer', 'exists:general_ledgers,id'],
            'escheatLiabilityId'           => ['nullable', 'integer', 'exists:general_ledgers,id'],
        ];
    }

    public function toInvestmentProductData(): array
    {
        $data = $this->validated();

        $mappings = [
            AccountProductType::ACCOUNT_CONTROL->name       => $data['savingsControlAccountId'],
            AccountProductType::INTEREST_ON_ACCOUNT->name   => $data['interestOnSavingsAccountId'],
            AccountProductType::INCOME_FROM_FEES->name      => $data['incomeFromFeeAccountId'],
            AccountProductType::INCOME_FROM_PENALTIES->name => $data['incomeFromPenaltyAccountId'],
            AccountProductType::TRANSFERS_SUSPENSE->name    => $data['transfersInSuspenseAccountId'],
        ];

        if (! empty($data['writeOffAccountId'])) {
            $mappings[AccountProductType::LOSSES_WRITTEN_OFF->name] = $data['writeOffAccountId'];
        }

        if (! empty($data['escheatLiabilityId'])) {
            $mappings[AccountProductType::ESCHEAT_LIABILITY->name] = $data['escheatLiabilityId'];
        }

        $data['general_ledgers'] = collect($mappings)->map(fn ($glId, $typeName) => [
            'financial_account_type' => $typeName,
            'general_ledger_id'      => $glId,
        ])->values()->all();

        unset(
            $data['savingsControlAccountId'],
            $data['interestOnSavingsAccountId'],
            $data['incomeFromFeeAccountId'],
            $data['incomeFromPenaltyAccountId'],
            $data['transfersInSuspenseAccountId'],
            $data['writeOffAccountId'],
            $data['escheatLiabilityId'],
        );

        return $data;
    }
}
