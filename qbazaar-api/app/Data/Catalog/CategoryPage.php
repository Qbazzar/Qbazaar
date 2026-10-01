<?php

declare(strict_types=1);

namespace App\Data\Catalog;

use App\Models\Ad;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

final readonly class CategoryPage
{
    /**
     * @param Collection<int, Category> $children
     * @param array<string, Collection<int, Ad>> $adsByChild keyed by child category id
     */
    public function __construct(
        public Category $category,
        public ?Category $parent,
        public Collection $children,
        public array $adsByChild,
    ) {}
}
