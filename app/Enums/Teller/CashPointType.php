<?php

declare(strict_types=1);

namespace App\Enums\Teller;

// The two kinds of cash point a teller can buy from / sell to.
enum CashPointType: string
{
    case VAULT = 'vault';
    case TELLER = 'teller';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
