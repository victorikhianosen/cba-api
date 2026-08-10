<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\GeneralLedger;
use App\Services\GeneralLedger\GeneralLedgerService;
use Illuminate\Database\Seeder;

class GeneralLedgerSeeder extends Seeder
{
    // Covers the GL roles required by AccountProductType and LoanProductType (matching Fineract's role names) plus common chart-of-accounts entries. Denominated in NGN — see USD_DEFINITIONS for the USD counterparts.
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

        // Wallet product roles (AccountProductType) — dedicated GLs, distinct from Savings/Current.
        ['Wallet Account Control', 'liability', 'savings_control'],
        ['Interest On Wallet Account', 'expense', 'interest_expense'],
        ['Wallet Account Income From Fees', 'income', 'fee_income'],
        ['Wallet Account Income From Penalties', 'income', 'penalty_income'],
        ['Wallet Account Transfers Suspense', 'liability', 'suspense'],

        // Virtual account product roles (AccountProductType + the dedicated
        // VIRTUAL_ACCOUNT_COLLECTIONS role). ACCOUNT_CONTROL/INTEREST_ON_ACCOUNT stay
        // mapped-but-dormant — a virtual account never carries its own balance, every
        // credit sweeps through Virtual Account Collections into its parent account.
        ['Virtual Account Control', 'liability', 'savings_control'],
        ['Interest On Virtual Account', 'expense', 'interest_expense'],
        ['Virtual Account Income From Fees', 'income', 'fee_income'],
        ['Virtual Account Income From Penalties', 'income', 'penalty_income'],
        ['Virtual Account Transfers Suspense', 'liability', 'suspense'],
        ['Virtual Account Collections', 'liability', 'suspense'],

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

        // Loan product roles (LoanProductType). Fund Source is
        // liability-classified, not asset — LoanService::approve()/LoanRepaymentService
        // use it as the posting party opposite the customer's account (debited on
        // disbursement, credited on repayment, matching this system's fixed
        // source=debit/destination=credit convention), the exact same role
        // "Investment Savings Control" (also liability) plays for investment funding in
        // the opposite direction. A liability-normal GL correctly shrinks on debit
        // (funding capacity drawn down) and grows on credit (capacity replenished).
        ['Loan Fund Source', 'liability', 'cash'],
        ['Loan Portfolio', 'asset', 'loan_portfolio'],
        ['Loan Interest On Loans', 'income', 'interest_income'],
        ['Loan Income From Fees', 'income', 'fee_income'],
        ['Loan Income From Penalties', 'income', 'penalty_income'],
        ['Loan Losses Written Off', 'expense', 'suspense'],
        ['Loan Transfers Suspense', 'liability', 'suspense'],
        ['Loan Overpayment', 'liability', 'payable'],
        ['Loan Interest Receivable', 'asset', 'receivable'],
        ['Loan Suspended Interest', 'liability', 'suspense'],

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

        // Wallet product roles (AccountProductType)
        ['USD Wallet Account Control', 'liability', 'savings_control'],
        ['USD Interest On Wallet Account', 'expense', 'interest_expense'],
        ['USD Wallet Account Income From Fees', 'income', 'fee_income'],
        ['USD Wallet Account Income From Penalties', 'income', 'penalty_income'],
        ['USD Wallet Account Transfers Suspense', 'liability', 'suspense'],

        // Virtual account product roles (AccountProductType + VIRTUAL_ACCOUNT_COLLECTIONS)
        ['USD Virtual Account Control', 'liability', 'savings_control'],
        ['USD Interest On Virtual Account', 'expense', 'interest_expense'],
        ['USD Virtual Account Income From Fees', 'income', 'fee_income'],
        ['USD Virtual Account Income From Penalties', 'income', 'penalty_income'],
        ['USD Virtual Account Transfers Suspense', 'liability', 'suspense'],
        ['USD Virtual Account Collections', 'liability', 'suspense'],

        // Loan product roles (LoanProductType) — see NGN block above for
        // why Fund Source is liability-classified, not asset.
        ['USD Loan Fund Source', 'liability', 'cash'],
        ['USD Loan Portfolio', 'asset', 'loan_portfolio'],
        ['USD Loan Interest On Loans', 'income', 'interest_income'],
        ['USD Loan Income From Fees', 'income', 'fee_income'],
        ['USD Loan Income From Penalties', 'income', 'penalty_income'],
        ['USD Loan Losses Written Off', 'expense', 'suspense'],
        ['USD Loan Transfers Suspense', 'liability', 'suspense'],
        ['USD Loan Overpayment', 'liability', 'payable'],
        ['USD Loan Interest Receivable', 'asset', 'receivable'],
        ['USD Loan Suspended Interest', 'liability', 'suspense'],

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

        $this->seedOpeningBalances();
    }

    // Every "cash"-typed GL (Cash in Vault, Loan Fund Source, Investment Savings
    // Reference, etc.) is a liquidity/funding-pool account that nothing else naturally
    // credits first — every other GL either starts at 0 and only ever receives money via
    // real postings (fees, penalties, control accounts growing as customers transact), or
    // is one of these pools that has to already hold something before it can ever act as
    // a posting *source* (GeneralLedgerPostingService::hasSufficientBalance() enforces a
    // hard ceiling — you can't debit/credit-out more than a GL currently holds). Real
    // banks capitalize these via an opening-balance journal entry that predates any
    // customer transaction; there's no prior transaction to route this through, so it's
    // set directly here — the standard "opening balance" seeding convention, not a
    // shortcut around GeneralLedgerPostingService (which remains the only place that
    // mutates a balance once the system is live).
    private function seedOpeningBalances(): void
    {
        GeneralLedger::where('type', 'cash')
            ->where('balance', 0)
            ->update(['balance' => 100_000_000]);
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
