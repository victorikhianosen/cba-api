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

        $ngn = Currency::where('code', 'NGN')->firstOrFail();
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $this->seedSavingsProduct($ngn);
        $this->seedCurrentProduct($ngn);
        $this->seedDomiciliaryProduct($usd);
        $this->seedWalletProduct($ngn);
        $this->seedVirtualProduct($ngn);
        $this->seedWalletProduct($usd, 'USD ');
        $this->seedVirtualProduct($usd, 'USD ');

        // Known pre-existing issue: AccountProductService::assertGeneralLedgerMappingsAreValid()
        // only accepts AccountProductType roles, but seedLoanProduct() maps
        // LoanProductFinancialAccountType roles (e.g. FUND_SOURCE) — throws on every run.
        // Guarded here so it can't take down the rest of DatabaseSeeder now that this
        // seeder runs automatically; not fixed because it's outside today's ask.
        try {
            $this->seedLoanProduct($ngn);
        } catch (\Throwable $e) {
            report($e);
        }

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

    private function seedLoanProduct(Currency $currency): void
    {
        if (AccountProduct::where('name', 'Personal Loan Product')->exists()) {
            return;
        }

        app(AccountProductService::class)->create([
            'name'            => 'Personal Loan Product',
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
