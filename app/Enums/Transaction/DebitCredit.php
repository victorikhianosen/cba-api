<?php

declare(strict_types=1);

namespace App\Enums\Transaction;

enum DebitCredit: string
{
    case DEBIT = 'debit';
    case CREDIT = 'credit';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
