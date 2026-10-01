<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Actions\Catalog\GetCategoryPageAction;
use App\Exceptions\DomainException;
use App\Http\Resources\Api\V1\Reference\CategoryPageResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Serialised category landing pages, identical for every visitor. Served
 * stale-while-revalidate, so a busy category is rebuilt at most once per
 * window and never by a crowd of requests at once. A taxonomy change moves
 * every page to a new key version.
 */
class CategoryPageCache
{
    private const VERSION_KEY = 'categories.page.version';

    public function __construct(private readonly GetCategoryPageAction $getCategoryPage) {}

    /**
     * @return array<string, mixed>
     *
     * @throws DomainException when the category does not exist or is hidden
     */
    public function get(string $slug): array
    {
        $fresh = (int) config('qbazaar.catalog.category_page_cache_seconds');

        /** @var array<string, mixed> $payload */
        $payload = Cache::flexible($this->key($slug), [$fresh, $fresh * 5], fn (): array => $this->render($slug));

        return $payload;
    }

    public function flush(): void
    {
        Cache::forever(self::VERSION_KEY, (string) Str::ulid());
    }

    /**
     * Rendered against a blank request so nothing about the visitor who
     * triggered a rebuild ends up in the shared payload.
     *
     * @return array<string, mixed>
     */
    private function render(string $slug): array
    {
        return (new CategoryPageResource($this->getCategoryPage->execute($slug)))->toArray(Request::create('/'));
    }

    private function key(string $slug): string
    {
        return 'categories.page.' . Cache::get(self::VERSION_KEY, '0') . '.' . sha1($slug);
    }
}
