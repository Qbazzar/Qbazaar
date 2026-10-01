<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\Catalog\CategoryHierarchy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An ad may only be filed under an active, publicly visible leaf category,
 * otherwise it would sit in a branch nobody can browse to or filter by.
 * `$current` lets an edit resubmit the category the ad already has.
 */
class SelectableCategory implements ValidationRule
{
    public function __construct(private readonly ?string $current = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === $this->current) {
            return;
        }

        if (! is_string($value) || ! app(CategoryHierarchy::class)->isSelectableLeaf($value)) {
            $fail('errors.validation.category_not_selectable')->translate();
        }
    }
}
