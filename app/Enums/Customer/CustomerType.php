<?php

declare(strict_types=1);

namespace App\Enums\Customer;

enum CustomerType: string
{
    case INDIVIDUAL = 'individual';
    case BUSINESS = 'business';
    case MINOR = 'minor';
    case GOVERNMENT = 'government';
    case CORPORATE = 'corporate';


    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    // Types that carry a business block, directors-free signatories, and a business-style display name.
    /** @return string[] */
    public static function businessLike(): array
    {
        return [self::BUSINESS->value, self::GOVERNMENT->value, self::CORPORATE->value];
    }
}
