<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects angle brackets in plain-text fields. Ad text is rendered by several
 * clients and embedded in JSON-LD, so refusing markup at the source is safer
 * than trusting every consumer to escape it.
 */
class NoMarkup implements ValidationRule
{
    public static function isFoundIn(mixed $value): bool
    {
        return is_string($value) && strpbrk($value, '<>') !== false;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (self::isFoundIn($value)) {
            $fail('errors.validation.no_markup')->translate();
        }
    }
}
