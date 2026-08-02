<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\GeneralLedger;
use App\Services\GeneralLedger\GeneralLedgerService;
use Illuminate\Database\Seeder;

class GeneralLedgerSeeder extends Seeder
{
    // Covers the GL roles required by AccountProductType and LoanProductFinancialAccountType (matching Fineract's role names) plus common chart-of-accounts entries. Denominated in NGN — see USD_DEFINITIONS for the USD counterparts.
    private const DEFINITIONS = [
        // name, classification, type

        // Savings/current product roles (AccountProductType)
        ['Savings Control', 'liability', 'savings_control'],
        ['Interest On Savings', 'expense', 'interest_expense'],
        ['Income From Fees', 'income', 'fee_income'],
        ['Income From Penalties', 'income', 'penalty_income'],
        ['Transfers Suspense', 'liability', 'suspense'],
        ['Overdraft Portfolio Control', 'asset', 'receivable'],
        ['Income From Interest', 'income', 'interest_income'],
        ['Losses Written Off', 'expense', 'suspense'],
        ['Escheat Liability', 'liability', 'payable'],

        // Current account product roles (dedicated GLs — a GL can only be
        // mapped to one product, so these are distinct from the savings ones)
        ['Current Account Control', 'liability', 'savings_control'],
        ['Interest On Current Account', 'expense', 'interest_expense'],
        ['Current Account Income From Fees', 'income', 'fee_income'],
        ['Current Account Income From Penalties', 'income', 'penalty_income'],
        ['Current Account Transfers Suspense', 'liability', 'suspense'],

        // Common chart-of-accounts entries not tied to a specific product role
        ['Cash in Vault', 'asset', 'cash'],
        ['Fixed Assets', 'asset', 'fixed_asset'],
        ['Suspense Account', 'asset', 'suspense'],
        ['Share Capital', 'equity', 'equity'],
        ['Retained Earnings', 'equity', 'equity'],
        ['Statutory Reserve', 'equity', 'equity'],
        ['Accounts Payable', 'liability', 'payable'],
        ['Tax Payable', 'liability', 'tax'],
        ['Other Operating Income', 'income', 'fee_income'],
        ['Operating Expenses', 'expense', 'interest_expense'],

        // Loan product roles (LoanProductFinancialAccountType)
        ['Loan Fund Source', 'asset', 'cash'],
        ['Loan Portfolio', 'asset', 'loan_portfolio'],
        ['Loan Interest On Loans', 'income', 'interest_income'],
        ['Loan Income From Fees', 'income', 'fee_income'],
        ['Loan Income From Penalties', 'income', 'penalty_income'],
        ['Loan Losses Written Off', 'expense', 'suspense'],
        ['Loan Transfers Suspense', 'liability', 'suspense'],
        ['Loan Overpayment', 'liability', 'payable'],

        // Investment product roles (shares AccountProductType,
        // but needs its own distinct GLs — a GL can only be mapped once).
        ['Investment Savings Reference', 'asset', 'cash'],
        ['Investment Savings Control', 'liability', 'savings_control'],
        ['Investment Interest On Savings', 'expense', 'interest_expense'],
        ['Investment Income From Fees', 'income', 'fee_income'],
        ['Investment Income From Penalties', 'income', 'penalty_income'],
        ['Investment Transfers Suspense', 'liability', 'suspense'],

        // Investment product roles (InvestmentProductType) not covered by the roles above.
        ['Investment Interest Payable', 'liability', 'payable'],
        ['Investment Interest Paid', 'asset', 'cash'],
        ['Investment Withholding Tax Payable', 'liability', 'tax'],
        ['Investment Settlement Maturity', 'asset', 'cash'],
    ];

    // USD counterparts of the product-role GLs above, prefixed "USD " since GL names must be globally unique and a GL can only be mapped to one product/currency.
    private const USD_DEFINITIONS = [
        // Savings/current product roles (AccountProductType)
        ['USD Savings Control', 'liability', 'savings_control'],
        ['USD Interest On Savings', 'expense', 'interest_expense'],
        ['USD Income From Fees', 'income', 'fee_income'],
        ['USD Income From Penalties', 'income', 'penalty_income'],
        ['USD Transfers Suspense', 'liability', 'suspense'],
        ['USD Overdraft Portfolio Control', 'asset', 'receivable'],
        ['USD Income From Interest', 'income', 'interest_income'],
        ['USD Losses Written Off', 'expense', 'suspense'],
        ['USD Escheat Liability', 'liability', 'payable'],

        // Domiciliary account product roles (dedicated GLs, same as Current's split from Savings)
        ['USD Domiciliary Account Control', 'liability', 'savings_control'],
        ['USD Interest On Domiciliary Account', 'expense', 'interest_expense'],
        ['USD Domiciliary Income From Fees', 'income', 'fee_income'],
        ['USD Domiciliary Income From Penalties', 'income', 'penalty_income'],
        ['USD Domiciliary Transfers Suspense', 'liability', 'suspense'],

        // Loan product roles (LoanProductFinancialAccountType)
        ['USD Loan Fund Source', 'asset', 'cash'],
        ['USD Loan Portfolio', 'asset', 'loan_portfolio'],
        ['USD Loan Interest On Loans', 'income', 'interest_income'],
        ['USD Loan Income From Fees', 'income', 'fee_income'],
        ['USD Loan Income From Penalties', 'income', 'penalty_income'],
        ['USD Loan Losses Written Off', 'expense', 'suspense'],
        ['USD Loan Transfers Suspense', 'liability', 'suspense'],
        ['USD Loan Overpayment', 'liability', 'payable'],

        // Investment product roles (shares AccountProductType)
        ['USD Investment Savings Control', 'liability', 'savings_control'],
        ['USD Investment Interest On Savings', 'expense', 'interest_expense'],
        ['USD Investment Income From Fees', 'income', 'fee_income'],
        ['USD Investment Income From Penalties', 'income', 'penalty_income'],
        ['USD Investment Transfers Suspense', 'liability', 'suspense'],

        // Investment product roles (InvestmentProductType) not covered by the roles above.
        ['USD Investment Interest Payable', 'liability', 'payable'],
        ['USD Investment Interest Paid', 'asset', 'cash'],
        ['USD Investment Withholding Tax Payable', 'liability', 'tax'],
        ['USD Investment Settlement Maturity', 'asset', 'cash'],
    ];

    // How many generic test GLs to generate per currency for ad hoc posting tests — cycled evenly across all 5 classifications.
    private const BULK_TEST_GL_COUNTS = [
        'NGN' => 10,
        'USD' => 20,
        'GBP' => 20,
    ];

    private const CLASSIFICATION_CYCLE = ['asset', 'liability', 'equity', 'income', 'expense'];

    public function run(): void
    {
        $ngn = Currency::firstOrCreate(
            ['code' => 'NGN'],
            ['name' => 'Naira', 'status' => 'active', 'is_base_currency' => true],
        );

        $usd = Currency::firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'status' => 'active', 'is_base_currency' => false],
        );

        $gbp = Currency::firstOrCreate(
            ['code' => 'GBP'],
            ['name' => 'Pound Sterling', 'status' => 'active', 'is_base_currency' => false],
        );

        $this->seedDefinitions(self::DEFINITIONS, $ngn);
        $this->seedDefinitions(self::USD_DEFINITIONS, $usd);

        $this->seedBulkTestLedgers($ngn, self::BULK_TEST_GL_COUNTS['NGN']);
        $this->seedBulkTestLedgers($usd, self::BULK_TEST_GL_COUNTS['USD']);
        $this->seedBulkTestLedgers($gbp, self::BULK_TEST_GL_COUNTS['GBP']);
    }

    // Generic numbered GLs (not tied to any product role) so there are enough distinct GLs per currency to exercise postings against — classification cycles evenly so both debit-normal and credit-normal balance behavior get covered.
    private function seedBulkTestLedgers(Currency $currency, int $count): void
    {
        $service = app(GeneralLedgerService::class);

        for ($i = 1; $i <= $count; $i++) {
            $classification = self::CLASSIFICATION_CYCLE[($i - 1) % count(self::CLASSIFICATION_CYCLE)];
            $normalizedName = strtolower($currency->code) . '_test_gl_' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);

            if (GeneralLedger::where('name', $normalizedName)->exists()) {
                continue;
            }

            $service->create([
                'name'           => $normalizedName,
                'classification' => $classification,
                'type'           => 'general',
                'currency_id'    => $currency->id,
                'status'         => 'active',
            ]);
        }
    }

    /** @param array<int, array{0: string, 1: string, 2: string}> $definitions */
    private function seedDefinitions(array $definitions, Currency $currency): void
    {
        $service = app(GeneralLedgerService::class);

        foreach ($definitions as [$name, $classification, $type]) {
            $normalizedName = strtolower(str_replace(' ', '_', trim($name)));

            if (GeneralLedger::where('name', $normalizedName)->exists()) {
                continue;
            }

            $service->create([
                'name'           => $normalizedName,
                'classification' => $classification,
                'type'           => $type,
                'currency_id'    => $currency->id,
                'status'         => 'active',
            ]);
        }
    }
}
