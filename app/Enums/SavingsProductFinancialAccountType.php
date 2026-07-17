<?php

namespace App\Enums;

/**
 * GL role vocabulary for deposit-family products (savings, current, merchant,
 * corporate, domiciliary) and investment/fixed-term products. Mirrors
 * Fineract's CashAccountsForSavings enum
 * (fineract-core/.../accounting/common/AccountingConstants.java) exactly,
 * including its numeric gaps — Fineract's Fixed Deposit Product accounting
 * reuses this same vocabulary rather than defining its own, so
 * InvestmentProductService uses this enum too.
 */
enum SavingsProductFinancialAccountType: int implements FinancialAccountTypeContract
{
    case SAVINGS_REFERENCE = 1;
    case SAVINGS_CONTROL = 2;
    case INTEREST_ON_SAVINGS = 3;
    case INCOME_FROM_FEES = 4;
    case INCOME_FROM_PENALTIES = 5;
    case TRANSFERS_SUSPENSE = 10;
    case OVERDRAFT_PORTFOLIO_CONTROL = 11;
    case INCOME_FROM_INTEREST = 12;
    case LOSSES_WRITTEN_OFF = 13;
    case ESCHEAT_LIABILITY = 14;

    public function label(): string
    {
        return match ($this) {
            self::SAVINGS_REFERENCE => 'Savings Reference',
            self::SAVINGS_CONTROL => 'Savings Control',
            self::INTEREST_ON_SAVINGS => 'Interest on Savings',
            self::INCOME_FROM_FEES => 'Income from Fees',
            self::INCOME_FROM_PENALTIES => 'Income from Penalties',
            self::TRANSFERS_SUSPENSE => 'Transfers Suspense',
            self::OVERDRAFT_PORTFOLIO_CONTROL => 'Overdraft Portfolio Control',
            self::INCOME_FROM_INTEREST => 'Income from Interest',
            self::LOSSES_WRITTEN_OFF => 'Losses Written Off',
            self::ESCHEAT_LIABILITY => 'Escheat Liability',
        };
    }

    /**
     * Mandatory for every deposit/investment product. OVERDRAFT_PORTFOLIO_CONTROL
     * is conditionally required only when allow_overdraft is true (handled by
     * the service, since that depends on product data, not the enum).
     * INCOME_FROM_INTEREST, LOSSES_WRITTEN_OFF, and ESCHEAT_LIABILITY are
     * accepted if supplied but never mandatory, matching Fineract.
     *
     * @return self[]
     */
    public static function required(): array
    {
        return [
            self::SAVINGS_REFERENCE,
            self::SAVINGS_CONTROL,
            self::INTEREST_ON_SAVINGS,
            self::INCOME_FROM_FEES,
            self::INCOME_FROM_PENALTIES,
            self::TRANSFERS_SUSPENSE,
        ];
    }

    /**
     * @return string[]
     */
    public static function names(): array
    {
        return array_map(fn (self $case) => $case->name, self::cases());
    }

    public static function fromName(string $name): self
    {
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        throw new \ValueError("'{$name}' is not a valid savings product financial account type.");
    }
}
