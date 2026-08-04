<?php

declare(strict_types=1);

namespace App\Enums\Teller;

enum CashierSessionStatus: string
{
    case OPEN = 'open';
    case PENDING_RECONCILIATION = 'pending_reconciliation';
    case PENDING_VARIANCE_APPROVAL = 'pending_variance_approval';
    case RECONCILED = 'reconciled';
    case CLOSED = 'closed';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
