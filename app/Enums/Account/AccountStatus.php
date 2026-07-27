<?php

declare(strict_types=1);

namespace App\Enums\Account;

enum AccountStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case DORMANT = 'dormant';
    case FROZEN = 'frozen';
    case RESTRICTED = 'restricted';
    case BLOCKED = 'blocked';
    case CLOSED = 'closed';
    case SUSPENDED = 'suspended';
    case DECEASED_HOLD = 'deceased_hold';
    case ESCHEATED = 'escheated';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
