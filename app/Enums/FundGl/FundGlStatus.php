<?php

declare(strict_types=1);

namespace App\Enums\FundGl;

enum FundGlStatus: string
{
    case PENDING_REVIEW = 'pending_review';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
