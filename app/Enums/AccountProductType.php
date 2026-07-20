<?php

namespace App\Enums;

/**
 * GL role vocabulary for deposit-family products (savings, current, merchant,
 * corporate, domiciliary) and investment/fixed-term products.
 */
enum AccountProductType: int
{
    case ACCOUNT_CONTROL = 1;
    case INTEREST_ON_ACCOUNT = 2;
    case INCOME_FROM_FEES = 3;
    case INCOME_FROM_PENALTIES = 4;
    case TRANSFERS_SUSPENSE = 5;
    case OVERDRAFT_PORTFOLIO_CONTROL = 6;
    case INCOME_FROM_INTEREST = 7;
    case LOSSES_WRITTEN_OFF = 8;
    case ESCHEAT_LIABILITY = 9;

    public function label(): string
    {
        return match ($this) {
            self::ACCOUNT_CONTROL => 'Account Control',
            self::INTEREST_ON_ACCOUNT => 'Interest on Account',
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
     * accepted if supplied but never mandatory.
     *
     * @return self[]
     */
    public static function required(): array
    {
        return [
            self::ACCOUNT_CONTROL,
            self::INTEREST_ON_ACCOUNT,
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

        throw new \ValueError("'{$name}' is not a valid account product financial account type.");
    }
}
