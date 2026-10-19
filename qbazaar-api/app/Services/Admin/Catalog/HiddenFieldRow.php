<?php

declare(strict_types=1);

namespace App\Services\Admin\Catalog;

/**
 * An inherited field the category does not show: hidden here, which the
 * admin can undo, or hidden higher up the tree.
 */
final readonly class HiddenFieldRow
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $hiddenHere,
        public ?string $hiddenByName,
    ) {}
}
