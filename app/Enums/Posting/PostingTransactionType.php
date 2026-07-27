<?php

declare(strict_types=1);

namespace App\Enums\Posting;

enum PostingTransactionType: string
{
    case TRANSFER = 'transfer';
    case DEPOSIT = 'deposit';
    case WITHDRAWAL = 'withdrawal';
    case FEE = 'fee';
    case INTEREST = 'interest';
    case REVERSAL = 'reversal';
    case ADJUSTMENT = 'adjustment';
    case REFUND = 'refund';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
