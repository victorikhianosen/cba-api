<?php

declare(strict_types=1);

namespace App\Enums\Customer;

enum CustomerType: string
{
    case INDIVIDUAL = 'individual';
    case BUSINESS = 'business';

    // A minor is not a separate customer type — it's an individual with guardian_id set.
    // Check that column, not customer_type, to tell them apart.

    // "Corporate" is not a separate customer type either — it's a business customer that
    // has director rows added, an optional addition available to any business-like
    // customer, not a distinct category. Check customer->directors()->exists(), not
    // customer_type, to tell a "corporate-style" business apart from one without directors.

    // "Government" is not a separate customer type either — App\Enums\Customer\LegalForm
    // already has a GOVERNMENT case, so a government entity is just a business customer
    // with legal_form = 'government'. Check that column, not customer_type.

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    // Types that carry a business block, directors-optional, signatory-carrying entity, and
    // a business-style display name.
    /** @return string[] */
    public static function businessLike(): array
    {
        return [self::BUSINESS->value];
    }
}
