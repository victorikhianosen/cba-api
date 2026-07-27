<?php

declare(strict_types=1);

namespace App\Enums\Transaction;

enum TransactionStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case REVERSED = 'reversed';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
