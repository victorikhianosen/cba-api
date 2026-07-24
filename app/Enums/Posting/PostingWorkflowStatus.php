<?php

declare(strict_types=1);

namespace App\Enums\Posting;

enum PostingWorkflowStatus: string
{
    case PENDING = 'pending';

    // case ACCEPTED = 'accepted';

    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
