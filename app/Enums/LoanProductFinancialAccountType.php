<?php

namespace App\Enums;

/**
 * GL role vocabulary for loan products. Mirrors Fineract's CashAccountsForLoan
 * enum (fineract-core/.../accounting/common/AccountingConstants.java) exactly,
 * including its numeric gaps — restricted here to the fields Fineract's own
 * loan-product creation API documents as mandatory for cash-based accounting
 * (fund source through overpayment). Charge-off/recovery/goodwill-credit
 * roles exist in Fineract for a more advanced workflow we haven't built and
 * are intentionally omitted.
 */
enum LoanProductFinancialAccountType: int
{
    case FUND_SOURCE = 1;
    case LOAN_PORTFOLIO = 2;
    case INTEREST_ON_LOANS = 3;
    case INCOME_FROM_FEES = 4;
    case INCOME_FROM_PENALTIES = 5;
    case LOSSES_WRITTEN_OFF = 6;
    case TRANSFERS_SUSPENSE = 10;
    case OVERPAYMENT = 11;

    public function label(): string
    {
        return match ($this) {
            self::FUND_SOURCE => 'Fund Source',
            self::LOAN_PORTFOLIO => 'Loan Portfolio',
            self::INTEREST_ON_LOANS => 'Interest on Loans',
            self::INCOME_FROM_FEES => 'Income from Fees',
            self::INCOME_FROM_PENALTIES => 'Income from Penalties',
            self::LOSSES_WRITTEN_OFF => 'Losses Written Off',
            self::TRANSFERS_SUSPENSE => 'Transfers Suspense',
            self::OVERPAYMENT => 'Overpayment',
        };
    }

    /**
     * All 8 roles are mandatory for cash-based loan accounting in Fineract.
     *
     * @return self[]
     */
    public static function required(): array
    {
        return self::cases();
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

        throw new \ValueError("'{$name}' is not a valid loan product financial account type.");
    }
}
