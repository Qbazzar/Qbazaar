<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The public (active) category tree, loaded with one query. An inactive
 * category hides its whole subtree.
 */
class CategoryTree
{
    public const CACHE_KEY = 'categories.tree';

    private const TTL_SECONDS = 3600;

    public function __construct(private readonly TreeAssembler $assembler) {}

    /**
     * @return Collection<int, Category>
     */
    public function roots(): Collection
    {
        /** @var Collection<int, Category> $roots */
        $roots = Cache::remember(
            self::CACHE_KEY,
            self::TTL_SECONDS,
            fn (): Collection => $this->assembler->nest(Category::query()->active()->orderBy('order')->get()),
        );

        return $roots;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
