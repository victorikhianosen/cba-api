<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\GeneralLedger;
use App\Services\GeneralLedger\GeneralLedgerService;
use Illuminate\Database\Seeder;

class GeneralLedgerSeeder extends Seeder
{
    /**
     * Definitions cover the GL roles required by SavingsProductFinancialAccountType
     * (shared by deposit + investment products) and LoanProductFinancialAccountType,
     * matching Fineract's real CashAccountsForSavings/CashAccountsForLoan role
     * names, plus common chart-of-accounts entries. All denominated in NGN.
     * Deposit, loan, and investment products each get their own distinct GLs
     * since a GL can only ever be mapped to one product.
     */
    private const DEFINITIONS = [
        // name, classification, type

        // Savings/current product roles (SavingsProductFinancialAccountType)
        ['Savings Reference', 'asset', 'cash'],
        ['Savings Control', 'liability', 'savings_control'],
        ['Interest On Savings', 'expense', 'interest_expense'],
        ['Income From Fees', 'income', 'fee_income'],
        ['Income From Penalties', 'income', 'penalty_income'],
        ['Transfers Suspense', 'liability', 'suspense'],
        ['Overdraft Portfolio Control', 'asset', 'receivable'],
        ['Income From Interest', 'income', 'interest_income'],
        ['Losses Written Off', 'expense', 'suspense'],
        ['Escheat Liability', 'liability', 'payable'],

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

        // Investment product roles (shares SavingsProductFinancialAccountType,
        // but needs its own distinct GLs — a GL can only be mapped once).
        ['Investment Savings Reference', 'asset', 'cash'],
        ['Investment Savings Control', 'liability', 'savings_control'],
        ['Investment Interest On Savings', 'expense', 'interest_expense'],
        ['Investment Income From Fees', 'income', 'fee_income'],
        ['Investment Income From Penalties', 'income', 'penalty_income'],
        ['Investment Transfers Suspense', 'liability', 'suspense'],
    ];

    public function run(): void
    {
        $currency = Currency::firstOrCreate(
            ['code' => 'NGN'],
            ['name' => 'Naira', 'status' => 'active', 'is_base_currency' => true],
        );

        $service = app(GeneralLedgerService::class);

        foreach (self::DEFINITIONS as [$name, $classification, $type]) {
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
