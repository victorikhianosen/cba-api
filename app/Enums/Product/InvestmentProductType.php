<?php

declare(strict_types=1);

namespace App\Enums\Product;

// GL role vocabulary for investment/fixed-term deposit products.
enum InvestmentProductType: int
{
    case INVESTMENT_FUNDING = 1;
    case INTEREST_EXPENSE = 2;
    case INTEREST_PAYABLE = 3;
    case EARLY_LIQUIDATION_PENALTY_INCOME = 4;
    case WITHHOLDING_TAX_PAYABLE = 5;

    public function label(): string
    {
        return match ($this) {
            // Used in both directions: credited when a deposit is funded, debited (paired
            // with a credit to the customer's account) when principal is repaid at
            // maturity — same GL, no separate "settlement" GL needed just because the
            // money is flowing the other way.
            self::INVESTMENT_FUNDING => 'Investment Funding',
            self::INTEREST_EXPENSE => 'Interest Expense',
            self::INTEREST_PAYABLE => 'Interest Payable / Accrued Interest',
            self::EARLY_LIQUIDATION_PENALTY_INCOME => 'Early Liquidation Penalty Income',
            self::WITHHOLDING_TAX_PAYABLE => 'Withholding Tax Payable',
        };
    }

    // Every role here is mandatory — each one maps directly to a real product setting
    // (interest_rate, early_liquidation_penalty_rate, withholding_tax_rate, tenure/maturity),
    // so there's no "optional" role left (unlike the account/loan product GL vocabularies).
    /** @return self[] */
    public static function required(): array
    {
        return self::cases();
    }

    /** @return string[] */
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

        throw new \ValueError("'{$name}' is not a valid investment product financial account type.");
    }
}
