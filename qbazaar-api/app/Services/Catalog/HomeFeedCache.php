<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Actions\Catalog\GetHomeFeedAction;
use App\Http\Resources\Api\V1\Home\HomeFeedResource;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The serialised home feed, identical for every visitor of the same language.
 * The catalog warmer rebuilds it on a schedule; requests only read it. Every
 * rebuild renders all languages from one query pass.
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
        return $this->cache->get(
            $this->key(app()->getLocale()),
            $this->ttlSeconds(),
            fn (): array => Locales::pick($this->refresh()),
        );
    }

    /**
     * @return array<string, array<string, mixed>> the stored feed of each language
     */
    public function refresh(): array
    {
        $feed = $this->getHomeFeed->execute();

        // Rendered against a blank request so nothing about the visitor who
        // happened to trigger a rebuild can end up in the shared payload.
        $request = Request::create('/');
        $payloads = Locales::each(fn (): array => (new HomeFeedResource($feed))->toArray($request));

        foreach ($payloads as $locale => $payload) {
            $this->cache->put($this->key($locale), $this->ttlSeconds(), fn (): array => $payload);
        }

        return $payloads;
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

    private function ttlSeconds(): int
    {
        return (int) config('qbazaar.home.cache_seconds');
    }
}
