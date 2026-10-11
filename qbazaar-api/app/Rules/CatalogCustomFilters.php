<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shape of the `custom_fields` filter of `/ads` and `/search`: a few
 * `key => text` equalities or `key => {min, max}` ranges. Keys that do
 * not parse are ignored by the filters themselves, so this only caps
 * how much a single request may ask for, and that a range has numeric bounds
 * with min not above max.
 */
class CatalogCustomFilters implements ValidationRule
{
    public const int MAX_FILTERS = 10;

    private const int MAX_VALUE_LENGTH = 100;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || count($value) > self::MAX_FILTERS) {
            $fail('validation.max.array')->translate(['max' => self::MAX_FILTERS, 'attribute' => $attribute]);

            return;
        }

        foreach ($value as $filter) {
            if (! $this->isValidFilter($filter)) {
                $fail('validation.array')->translate(['attribute' => $attribute]);

                return;
            }
        }
    }

    private function isValidFilter(mixed $filter): bool
    {
        if (is_string($filter)) {
            return mb_strlen($filter) <= self::MAX_VALUE_LENGTH;
        }

        return is_array($filter) && $this->isValidRange($filter);
    }

    /**
     * @param array<mixed> $range
     */
    private function isValidRange(array $range): bool
    {
        if (array_diff(array_keys($range), ['min', 'max']) !== []) {
            return false;
        }

        foreach ($range as $bound) {
            if (! is_numeric($bound)) {
                return false;
            }
        }

        return ! isset($range['min'], $range['max']) || (float) $range['min'] <= (float) $range['max'];
    }
}
