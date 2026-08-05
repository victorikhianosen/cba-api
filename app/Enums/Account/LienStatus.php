<?php

declare(strict_types=1);

namespace App\Enums\Account;

enum LienStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case RELEASED = 'released';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
