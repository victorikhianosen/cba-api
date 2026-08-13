<?php

declare(strict_types=1);

namespace App\Enums\Product;

// GL role vocabulary for loan products, mirrors Fineract's CashAccountsForLoan enum (numeric gaps included); charge-off/recovery/goodwill-credit roles are intentionally omitted (not built yet).
enum LoanProductType: int
{
    // 1 (formerly FUND_SOURCE) intentionally retired — disbursement/repayment/top-up/
    // refinance-payoff all post directly against LOAN_PORTFOLIO now instead of this
    // placeholder liquidity-pool GL, which produced an unbalanced third journal leg (see
    // LoanTransactionService::moveLoanPortfolio()). Left as a gap, not reused, so no
    // historical ProductGlMapping row is ever misread.
    case LOAN_PORTFOLIO = 2;
    case INTEREST_ON_LOANS = 3;
    case INCOME_FROM_FEES = 4;
    case INCOME_FROM_PENALTIES = 5;
    case LOSSES_WRITTEN_OFF = 6;
    // 10 (formerly TRANSFERS_SUSPENSE) intentionally retired — confirmed unused by any
    // loan posting logic; that's an Account/Investment product concept (in-flight
    // transfer holding), not a loan one. Left as a gap rather than reused so no
    // historical ProductGlMapping row is ever misread.
    case OVERPAYMENT = 11;

    // Accrual-side counterpart to INTEREST_ON_LOANS (which becomes purely the
    // income-recognition GL once accrual posting exists) — the asset GL representing
    // interest owed but not yet collected.
    case INTEREST_RECEIVABLE = 12;
    // Holding GL for interest that's been accrued but, because the loan is
    // non-performing, isn't being recognized as income yet — money the customer
    // conceptually owes that hasn't been booked to P&L.
    case SUSPENDED_INTEREST = 13;

    // 14 (formerly PROVISION) intentionally retired — the ECL/provisioning feature was
    // removed; write-offs post the full amount straight to LOSSES_WRITTEN_OFF now.
    case RECOVERY_INCOME = 15;   // income — money collected on an already-written-off loan

    // Management/Processing are the only two configurable fee types — first-class
    // product-level fields (management_fee_percentage/processing_fee_percentage), each
    // with its own mandatory dedicated GL.
    case MANAGEMENT_FEE_INCOME = 16;
    case PROCESSING_FEE_INCOME = 17;

    public function label(): string
    {
        return match ($this) {
            self::LOAN_PORTFOLIO => 'Loan Portfolio',
            self::INTEREST_ON_LOANS => 'Interest on Loans',
            self::INCOME_FROM_FEES => 'Income from Fees',
            self::INCOME_FROM_PENALTIES => 'Income from Penalties',
            self::LOSSES_WRITTEN_OFF => 'Losses Written Off',
            self::OVERPAYMENT => 'Overpayment',
            self::INTEREST_RECEIVABLE => 'Interest Receivable',
            self::SUSPENDED_INTEREST => 'Suspended Interest',
            self::RECOVERY_INCOME => 'Recovery Income',
            self::MANAGEMENT_FEE_INCOME => 'Management Fee Income',
            self::PROCESSING_FEE_INCOME => 'Processing Fee Income',
        };
    }

    // All roles are mandatory for cash-based loan accounting in Fineract, plus the two
    // receivable/suspension roles this system added on top.
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

        throw new \ValueError("'{$name}' is not a valid loan product financial account type.");
    }
}
