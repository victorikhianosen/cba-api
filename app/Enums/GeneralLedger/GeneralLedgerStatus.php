<?php

declare(strict_types=1);

namespace App\Enums\GeneralLedger;

enum GeneralLedgerStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case REJECTED = 'rejected';
    case ASSIGNED = 'assigned';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
