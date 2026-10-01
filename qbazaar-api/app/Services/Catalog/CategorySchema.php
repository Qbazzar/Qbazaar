<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Per-slug custom-field and filter definitions, cached for the posting form
 * and the search filters.
 *
 * Every entry key carries a generation token, and {@see flush()} swaps the
 * token, so one call retires the entries of every slug, including slugs
 * that were renamed or deleted in bulk. Retired entries simply age out.
 * Unknown slugs are never cached, so random paths cannot fill the store.
 */
class CategorySchema
{
    private const TTL_SECONDS = 3600;

    private const GENERATION_KEY = 'categories.schema.generation';

    /**
     * @return array{fields: array<int, array<string, mixed>>, filters: array<int, array<string, mixed>>}
     *
     * @throws DomainException
     */
    public function for(string $slug): array
    {
        $key = sprintf('categories.schema.%s.%s', $this->generation(), $slug);

        /** @var array{fields: array<int, array<string, mixed>>, filters: array<int, array<string, mixed>>}|null $schema */
        $schema = Cache::get($key);

        if (is_array($schema)) {
            return $schema;
        }

        /** @var Category|null $category */
        $category = Category::query()->where('slug', $slug)->first(['id', 'custom_fields', 'custom_filters']);

        if ($category === null) {
            throw new DomainException(ErrorCode::CATEGORY_NOT_FOUND);
        }

        $schema = [
            'fields' => $category->custom_fields ?? [],
            'filters' => $category->custom_filters ?? [],
        ];

        Cache::put($key, $schema, self::TTL_SECONDS);

        return $schema;
    }

    public function flush(): void
    {
        Cache::forever(self::GENERATION_KEY, Str::random(12));
    }

    private function generation(): string
    {
        return (string) Cache::rememberForever(self::GENERATION_KEY, static fn (): string => Str::random(12));
    }
}
