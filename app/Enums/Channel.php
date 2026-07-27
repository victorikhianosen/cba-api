<?php

declare(strict_types=1);

namespace App\Enums;

enum Channel: string
{
    case WEB = 'web';
    case MOBILE = 'mobile';
    case USSD = 'ussd';
    case POS = 'pos';
    case API = 'api';
    case ATM = 'atm';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
