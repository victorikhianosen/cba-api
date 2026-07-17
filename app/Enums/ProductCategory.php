<?php

namespace App\Enums;

/**
 * Product-type vocabulary for the account_products table (savings/current/
 * merchant/corporate/domiciliary — the deposit-account family). Loan and
 * investment products live in their own dedicated tables/models/services
 * (LoanProduct, InvestmentProduct) and no longer route through this enum.
 */
enum ProductCategory: string
{
    case DEPOSIT = 'deposit';

    /**
     * @return string[]
     */
    public function productTypes(): array
    {
        return match ($this) {
            self::DEPOSIT => ['savings', 'current', 'merchant', 'corporate', 'domiciliary'],
        };
    }
}
