<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\Catalog\LocationHierarchy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An ad may only be placed in a location that exists and is active along
 * with every location above it, i.e. one the public tree still shows.
 * `$current` lets an edit resubmit the location the ad already has, so a
 * deactivated location does not block an unrelated change.
 */
class SelectableLocation implements ValidationRule
{
    public function __construct(private readonly ?string $current = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === $this->current) {
            return;
        }

        if (! is_string($value) || ! app(LocationHierarchy::class)->index()->isVisible($value)) {
            $fail('errors.validation.location_not_selectable')->translate();
        }
    }
}
