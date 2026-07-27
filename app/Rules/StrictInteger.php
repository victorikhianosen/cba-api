<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// Rejects numeric strings and floats that Laravel's built-in 'integer' rule lets through via loose coercion — money fields (minor units) must arrive as a real JSON integer.
class StrictInteger implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_int($value)) {
            $fail("The {$attribute} must be an integer, not a string or decimal.");
        }
    }
}
