<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Product\AccountProductType;
use App\Enums\Product\LoanProductFinancialAccountType;
use App\Models\AccountProduct;
use App\Models\Currency;
use App\Models\GeneralLedger;
use App\Services\AccountProduct\AccountProductService;
use Illuminate\Database\Seeder;

class AccountProductSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(GeneralLedgerSeeder::class);

        $currency = Currency::where('code', 'NGN')->firstOrFail();

        $this->seedSavingsProduct($currency);
        $this->seedCurrentProduct($currency);
        $this->seedLoanProduct($currency);
        $this->seedInvestmentProduct($currency);
    }

    private function seedSavingsProduct(Currency $currency): void
    {
        if (AccountProduct::where('name', 'basic_savings_account')->exists()) {
            return;
        }

        app(AccountProductService::class)->create([
            'name'                          => 'basic_savings_account',
            'product_type'                  => 'savings',
            'description'                   => 'Standard retail savings product seeded for testing.',
            'currency_id'                   => $currency->id,
            'interest_rate'                 => 2.5,
            'interest_type'                 => 'daily_balance',
            'interest_compounding_period'   => 'monthly',
            'interest_posting_period'       => 'monthly',
            'min_required_opening_balance'  => 1000,
            'min_required_balance'          => 500,
            'enforce_min_required_balance'  => true,
            'withhold_tax'                  => true,
            'dormancy_period_days'          => 365,
            'general_ledgers'               => $this->mappingsFor(AccountProductType::required(), [
                'ACCOUNT_CONTROL'       => 'savings_control',
                'INTEREST_ON_ACCOUNT'   => 'interest_on_savings',
                'INCOME_FROM_FEES'      => 'income_from_fees',
                'INCOME_FROM_PENALTIES' => 'income_from_penalties',
                'TRANSFERS_SUSPENSE'    => 'transfers_suspense',
            ]),
        ]);
    }

    private function seedCurrentProduct(Currency $currency): void
    {
        if (AccountProduct::where('name', 'naira_current_account')->exists()) {
            return;
        }

        $accountProduct = app(AccountProductService::class)->create([
            'name'                         => 'naira_current_account',
            'product_type'                 => 'current',
            'description'                  => 'Standard Naira current account product, auto-assigned to business customers on approval.',
            'currency_id'                  => $currency->id,
            'interest_rate'                => 0,
            'min_required_opening_balance' => 5000,
            'min_required_balance'         => 0,
            'general_ledgers'              => $this->mappingsFor(AccountProductType::required(), [
                'ACCOUNT_CONTROL'       => 'current_account_control',
                'INTEREST_ON_ACCOUNT'   => 'interest_on_current_account',
                'INCOME_FROM_FEES'      => 'current_account_income_from_fees',
                'INCOME_FROM_PENALTIES' => 'current_account_income_from_penalties',
                'TRANSFERS_SUSPENSE'    => 'current_account_transfers_suspense',
            ]),
        ]);

        app(AccountProductService::class)->approve($accountProduct);
    }

    private function seedLoanProduct(Currency $currency): void
    {
        if (AccountProduct::where('name', 'personal_loan_product')->exists()) {
            return;
        }

        app(AccountProductService::class)->create([
            'name'            => 'personal_loan_product',
            'product_type'    => 'loan',
            'description'     => 'Unsecured personal loan product seeded for testing.',
            'currency_id'     => $currency->id,
            'interest_rate'   => 18,
            'general_ledgers' => $this->mappingsFor(LoanProductFinancialAccountType::required(), [
                'FUND_SOURCE'          => 'loan_fund_source',
                'LOAN_PORTFOLIO'       => 'loan_portfolio',
                'INTEREST_ON_LOANS'    => 'loan_interest_on_loans',
                'INCOME_FROM_FEES'     => 'loan_income_from_fees',
                'INCOME_FROM_PENALTIES' => 'loan_income_from_penalties',
                'LOSSES_WRITTEN_OFF'   => 'loan_losses_written_off',
                'TRANSFERS_SUSPENSE'   => 'loan_transfers_suspense',
                'OVERPAYMENT'          => 'loan_overpayment',
            ]),
        ]);
    }

    private function seedInvestmentProduct(Currency $currency): void
    {
        if (AccountProduct::where('name', 'fixed_term_investment_product')->exists()) {
            return;
        }

        app(AccountProductService::class)->create([
            'name'            => 'fixed_term_investment_product',
            'product_type'    => 'investment',
            'description'     => 'Fixed-term investment/deposit product seeded for testing.',
            'currency_id'     => $currency->id,
            'interest_rate'   => 12,
            'general_ledgers' => $this->mappingsFor(AccountProductType::required(), [
                'ACCOUNT_CONTROL'       => 'investment_savings_control',
                'INTEREST_ON_ACCOUNT'   => 'investment_interest_on_savings',
                'INCOME_FROM_FEES'      => 'investment_income_from_fees',
                'INCOME_FROM_PENALTIES' => 'investment_income_from_penalties',
                'TRANSFERS_SUSPENSE'    => 'investment_transfers_suspense',
            ]),
        ]);
    }

    /**
     * @param AccountProductType[]|LoanProductFinancialAccountType[] $types
     * @param array<string, string> $glNameByType
     */
    private function mappingsFor(array $types, array $glNameByType): array
    {
        return collect($types)
            ->map(function ($type) use ($glNameByType) {
                $ledger = GeneralLedger::where('name', $glNameByType[$type->name])->firstOrFail();

                return [
                    'financial_account_type' => $type->name,
                    'general_ledger_id'      => $ledger->id,
                ];
            })
            ->all();
    }
}
