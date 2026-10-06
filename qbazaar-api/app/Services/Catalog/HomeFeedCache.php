<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Actions\Catalog\GetHomeFeedAction;
use App\Data\Catalog\HomeFeed;
use App\Http\Resources\Api\V1\Home\HomeFeedResource;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The serialised home feed, identical for every visitor of the same language.
 * The catalog warmer rebuilds it on a schedule; requests only read it.
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
        $locale = app()->getLocale();

        return $this->cache->get(
            $this->key($locale),
            $this->ttlSeconds(),
            fn (): array => $this->render($this->getHomeFeed->execute(), $locale),
        );
    }

    /** One query pass feeds every language. */
    public function refresh(): void
    {
        $feed = $this->getHomeFeed->execute();

        foreach (Locales::supported() as $locale) {
            $this->cache->put($this->key($locale), $this->ttlSeconds(), fn (): array => $this->render($feed, $locale));
        }
    }

    public function flush(): void
    {
        foreach (Locales::supported() as $locale) {
            Cache::forget($this->key($locale));
        }
    }

    public function key(string $locale): string
    {
        return CatalogCache::HOME_FEED_KEY . '.' . $locale;
    }

    /**
     * Rendered against a blank request so nothing about the visitor who
     * happened to trigger a rebuild can end up in the shared payload.
     *
     * @return array<string, mixed>
     */
    private function render(HomeFeed $feed, string $locale): array
    {
        return Locales::within(
            $locale,
            fn (): array => (new HomeFeedResource($feed))->toArray(Request::create('/')),
        );
    }

    private function ttlSeconds(): int
    {
        return (int) config('qbazaar.home.cache_seconds');
    }
}
