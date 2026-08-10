<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Product\AccountProductType;
use App\Enums\Product\LoanProductType;
use App\Models\AccountProduct;
use App\Models\Currency;
use App\Models\GeneralLedger;
use App\Models\LoanProduct;
use App\Services\AccountProduct\AccountProductService;
use App\Services\LoanProduct\LoanProductService;
use Illuminate\Database\Seeder;

class AccountProductSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(GeneralLedgerSeeder::class);

        $ngn = Currency::where('code', 'NGN')->firstOrFail();
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $this->seedSavingsProduct($ngn);
        $this->seedCurrentProduct($ngn);
        $this->seedDomiciliaryProduct($usd);
        $this->seedWalletProduct($ngn);
        $this->seedVirtualProduct($ngn);
        $this->seedWalletProduct($usd, 'USD ');
        $this->seedVirtualProduct($usd, 'USD ');

        $this->seedLoanProduct($ngn);

        try {
            $this->seedInvestmentProduct($ngn);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function seedSavingsProduct(Currency $currency): void
    {
        if (AccountProduct::where('name', 'Basic Savings Account')->exists()) {
            return;
        }

        app(AccountProductService::class)->create([
            'name'                          => 'Basic Savings Account',
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
        if (AccountProduct::where('name', 'Naira Current Account')->exists()) {
            return;
        }

        $accountProduct = app(AccountProductService::class)->create([
            'name'                         => 'Naira Current Account',
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

    private function seedDomiciliaryProduct(Currency $currency): void
    {
        if (AccountProduct::where('name', 'USD Domiciliary Account')->exists()) {
            return;
        }

        $accountProduct = app(AccountProductService::class)->create([
            'name'                         => 'USD Domiciliary Account',
            'product_type'                 => 'domiciliary',
            'description'                  => 'USD domiciliary account product for customers holding foreign-currency balances.',
            'currency_id'                  => $currency->id,
            'interest_rate'                => 0,
            'min_required_opening_balance' => 100,
            'min_required_balance'         => 0,
            'general_ledgers'              => $this->mappingsFor(AccountProductType::required(), [
                'ACCOUNT_CONTROL'       => 'usd_domiciliary_account_control',
                'INTEREST_ON_ACCOUNT'   => 'usd_interest_on_domiciliary_account',
                'INCOME_FROM_FEES'      => 'usd_domiciliary_income_from_fees',
                'INCOME_FROM_PENALTIES' => 'usd_domiciliary_income_from_penalties',
                'TRANSFERS_SUSPENSE'    => 'usd_domiciliary_transfers_suspense',
            ]),
        ]);

        app(AccountProductService::class)->approve($accountProduct);
    }

    // $glPrefix mirrors the "USD " convention already used for Domiciliary's GLs — the
    // same product type can be seeded once per currency, each with its own dedicated GLs
    // (a GL can only be mapped to one product), so Wallet/Virtual are just as multi-currency
    // as every other deposit product here, not NGN-only.
    private function seedWalletProduct(Currency $currency, string $glPrefix = ''): void
    {
        $name = trim("{$glPrefix}Digital Wallet Account");

        if (AccountProduct::where('name', $name)->exists()) {
            return;
        }

        $glKey = fn (string $suffix) => strtolower(str_replace(' ', '_', trim("{$glPrefix}{$suffix}")));

        $accountProduct = app(AccountProductService::class)->create([
            'name'                         => $name,
            'product_type'                 => 'wallet',
            'description'                  => 'General-purpose digital wallet product — the typical settlement/parent account for a customer\'s virtual accounts.',
            'currency_id'                  => $currency->id,
            'interest_rate'                => 0,
            'min_required_opening_balance' => 0,
            'min_required_balance'         => 0,
            'general_ledgers'              => $this->mappingsFor(AccountProductType::required(), [
                'ACCOUNT_CONTROL'       => $glKey('Wallet Account Control'),
                'INTEREST_ON_ACCOUNT'   => $glKey('Interest On Wallet Account'),
                'INCOME_FROM_FEES'      => $glKey('Wallet Account Income From Fees'),
                'INCOME_FROM_PENALTIES' => $glKey('Wallet Account Income From Penalties'),
                'TRANSFERS_SUSPENSE'    => $glKey('Wallet Account Transfers Suspense'),
            ]),
        ]);

        app(AccountProductService::class)->approve($accountProduct);
    }

    private function seedVirtualProduct(Currency $currency, string $glPrefix = ''): void
    {
        $name = trim("{$glPrefix}Virtual Collection Account");

        if (AccountProduct::where('name', $name)->exists()) {
            return;
        }

        $glKey = fn (string $suffix) => strtolower(str_replace(' ', '_', trim("{$glPrefix}{$suffix}")));
        $roles = [...AccountProductType::required(), AccountProductType::VIRTUAL_ACCOUNT_COLLECTIONS];

        $accountProduct = app(AccountProductService::class)->create([
            'name'                         => $name,
            'product_type'                 => 'virtual',
            'description'                  => 'Dedicated virtual/collection sub-account product (Paystack/Monnify-style) — balances auto-sweep to a designated parent settlement account.',
            'currency_id'                  => $currency->id,
            'interest_rate'                => 0,
            'allow_overdraft'              => false,
            'min_required_opening_balance' => 0,
            'min_required_balance'         => 0,
            'general_ledgers'              => $this->mappingsFor($roles, [
                'ACCOUNT_CONTROL'             => $glKey('Virtual Account Control'),
                'INTEREST_ON_ACCOUNT'         => $glKey('Interest On Virtual Account'),
                'INCOME_FROM_FEES'            => $glKey('Virtual Account Income From Fees'),
                'INCOME_FROM_PENALTIES'       => $glKey('Virtual Account Income From Penalties'),
                'TRANSFERS_SUSPENSE'          => $glKey('Virtual Account Transfers Suspense'),
                'VIRTUAL_ACCOUNT_COLLECTIONS' => $glKey('Virtual Account Collections'),
            ]),
        ]);

        app(AccountProductService::class)->approve($accountProduct);
    }

    // Was wrongly wired through AccountProductService (whose
    // assertGeneralLedgerMappingsAreValid() only accepts AccountProductType roles, not
    // LoanProductType ones — threw on every run, silently swallowed by a
    // try/catch in run()). loan_products is its own dedicated table/service, unrelated to
    // account_products — fixed to go through LoanProductService::create() instead.
    private function seedLoanProduct(Currency $currency): void
    {
        if (LoanProduct::where('name', 'Personal Loan Product')->exists()) {
            return;
        }

        $loanProduct = app(LoanProductService::class)->create([
            'name'                               => 'Personal Loan Product',
            'description'                        => 'Unsecured personal loan product seeded for testing — 24% p.a., 12 monthly installments, reducing-balance amortization.',
            'currency_id'                        => $currency->id,
            'interest_rate'                      => 24,
            'interest_rate_frequency_type'       => 'per_year',
            'interest_method'                    => 'declining_balance',
            'interest_calculation_period_type'   => 'same_as_repayment_period',
            'interest_calculation_days_in_year'  => '365',
            'min_principal_amount'               => 50000,
            'max_principal_amount'               => 5000000,
            'default_principal_amount'           => 1000000,
            'repayment_every'                    => 1,
            'repayment_frequency_type'            => 'months',
            'number_of_repayments'                => 12,
            'min_number_of_repayments'            => 3,
            'max_number_of_repayments'            => 24,
            'amortization_method'                => 'equal_installments',
            'days_in_month_type'                 => 'actual',
            'penalty_interest_rate'              => 5,
            'payment_allocation_order'           => ['penalty', 'fee', 'interest', 'principal'],
            'general_ledgers' => $this->mappingsFor(LoanProductType::required(), [
                'FUND_SOURCE'            => 'loan_fund_source',
                'LOAN_PORTFOLIO'         => 'loan_portfolio',
                'INTEREST_ON_LOANS'      => 'loan_interest_on_loans',
                'INCOME_FROM_FEES'       => 'loan_income_from_fees',
                'INCOME_FROM_PENALTIES'  => 'loan_income_from_penalties',
                'LOSSES_WRITTEN_OFF'     => 'loan_losses_written_off',
                'TRANSFERS_SUSPENSE'     => 'loan_transfers_suspense',
                'OVERPAYMENT'            => 'loan_overpayment',
                'INTEREST_RECEIVABLE'    => 'loan_interest_receivable',
                'SUSPENDED_INTEREST'     => 'loan_suspended_interest',
            ]),
        ]);

        app(LoanProductService::class)->approve($loanProduct);
    }

    private function seedInvestmentProduct(Currency $currency): void
    {
        if (AccountProduct::where('name', 'Fixed Term Investment Product')->exists()) {
            return;
        }

        app(AccountProductService::class)->create([
            'name'            => 'Fixed Term Investment Product',
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
     * @param AccountProductType[]|LoanProductType[] $types
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
