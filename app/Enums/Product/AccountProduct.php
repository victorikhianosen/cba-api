<?php

declare(strict_types=1);

namespace App\Enums\Product;

enum AccountProduct: string
{
    case SAVINGS = 'savings';
    case CURRENT = 'current';
    case DOMICILIARY = 'domiciliary';
    // case MERCHANT = 'merchant';
    // case CORPORATE = 'corporate';
    // case VRITUAL = 'virtual';
    // case WALLET = 'wallet';


    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }
}
