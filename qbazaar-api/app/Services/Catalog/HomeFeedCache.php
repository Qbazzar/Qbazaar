<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Actions\Catalog\GetHomeFeedAction;
use App\Http\Resources\Api\V1\Home\HomeFeedResource;
use Illuminate\Http\Request;

/**
 * The serialised home feed, identical for every visitor. The catalog warmer
 * rebuilds it on a schedule; requests only read it.
 */
class HomeFeedCache
{
    public function __construct(
        private readonly GetHomeFeedAction $getHomeFeed,
        private readonly WarmedCache $cache,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->cache->get(CatalogCache::HOME_FEED_KEY, $this->ttlSeconds(), $this->render(...));
    }

    public function refresh(): void
    {
        $this->cache->put(CatalogCache::HOME_FEED_KEY, $this->ttlSeconds(), $this->render(...));
    }

    /**
     * Rendered against a blank request so nothing about the visitor who
     * happened to trigger a rebuild can end up in the shared payload.
     *
     * @return array<string, mixed>
     */
    private function render(): array
    {
        return (new HomeFeedResource($this->getHomeFeed->execute()))->toArray(Request::create('/'));
    }

    private function ttlSeconds(): int
    {
        return (int) config('qbazaar.home.cache_seconds');
    }
}
