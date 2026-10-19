<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\Catalog\Taxonomy\TaxonomyKind;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A category or location may not be moved under itself or anything below
 * it: the tree would get a loop that no public page could reach. Read from
 * the cached hierarchy, so the check costs no query once warm.
 */
class NotInOwnSubtree implements ValidationRule
{
    public function __construct(
        private readonly TaxonomyKind $kind,
        private readonly ?string $nodeId,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->nodeId === null || ! is_string($value) || $value === '') {
            return;
        }

        if (in_array($value, $this->kind->hierarchy()->descendantsOf($this->nodeId), true)) {
            $fail('admin.catalog.validation.parent_in_subtree')->translate();
        }
    }
}
