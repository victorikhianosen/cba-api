<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects numeric strings ("1000") and floats (1000.5) that Laravel's
 * built-in 'integer' rule lets through via loose type coercion — money
 * fields must arrive as a real JSON integer, not anything merely
 * is_numeric(). Values are minor units (e.g. kobo/cents), so a fractional
 * amount is never valid either.
 */
class StrictInteger implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_int($value)) {
            $fail("The {$attribute} must be an integer, not a string or decimal.");
        }
    }
}
