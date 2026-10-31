<?php

declare(strict_types=1);

namespace App\Domain\ProvablyFair\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A house edge the crash maths can use: the derived 1-in-N divisor, round(1 / edge),
 * must be at least 2 (edge ≤ ~2/3) and fit in an integer. Anything else used to reach
 * ProvablyFairService and fail with a 500.
 */
final class ValidHouseEdge implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value) || (float) $value <= 0.0 || (float) $value >= 1.0) {
            $fail('The :attribute must be between 0 and 1.');

            return;
        }

        $divisor = round(1.0 / (float) $value);

        if ($divisor < 2 || $divisor >= (float) PHP_INT_MAX) {
            $fail('The :attribute is outside the range a 1-in-N house edge can express.');
        }
    }
}
