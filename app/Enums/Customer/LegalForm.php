<?php

declare(strict_types=1);

namespace App\Enums\Customer;

enum LegalForm: string
{
    case NOT_REGISTERED = 'not_registered';
    case SOLE_PROPRIETORSHIP = 'sole_proprietorship';
    case PARTNERSHIP = 'partnership';
    case LIMITED_LIABILITY = 'limited_liability';
    case NGO = 'ngo';
    case COOPERATIVE = 'cooperative';
    case GOVERNMENT = 'government';
    case CHURCH = 'church';
    case ASSOCIATION = 'association';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
