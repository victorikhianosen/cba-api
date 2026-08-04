<?php

declare(strict_types=1);

namespace App\Enums\Teller;

enum CashierTransactionType: string
{
    case CASH_BUY = 'cash_buy';
    case CASH_SELL = 'cash_sell';
    case VARIANCE_ADJUSTMENT = 'variance_adjustment';
    // Customer-facing counter transactions — not internal vault/teller cash movement.
    case CASH_DEPOSIT = 'cash_deposit';
    case CASH_WITHDRAWAL = 'cash_withdrawal';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
