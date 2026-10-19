<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Enums\CategoryFieldType;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Per-slug custom-field and filter definitions, cached for the posting form
 * and the search filters. Fields include the ones inherited from ancestors
 * ({@see CategoryFields}); filters are the fields marked filterable.
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

    public function __construct(private readonly CategoryFields $fields) {}

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

        $categoryId = Category::query()->where('slug', $slug)->value('id');

        if (! is_string($categoryId)) {
            throw new DomainException(ErrorCode::CATEGORY_NOT_FOUND);
        }

        $schema = [
            'fields' => $this->fields->effective($categoryId),
            'filters' => $this->fields->filters($categoryId),
        ];

        Cache::put($key, $schema, self::TTL_SECONDS);

        return $schema;
    }

    /**
     * Keys of the category's filterable single-choice fields, the ones whose
     * options get ad counts next to them. Looked up by id because search
     * filters by id.
     *
     * @return list<string>
     */
    public function selectFilterKeys(string $categoryId): array
    {
        $keys = [];

        foreach ($this->fields->effective($categoryId) as $field) {
            $name = (string) $field['key'];

            if ($field['filterable'] === true && $field['type'] === CategoryFieldType::SELECT->value && preg_match('/^[a-z0-9_]+$/i', $name) === 1) {
                $keys[] = $name;
            }
        }

        return array_slice($keys, 0, (int) config('qbazaar.search.facet_custom_fields_max'));
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
