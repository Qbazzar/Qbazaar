<?php

declare(strict_types=1);

namespace App\Services\Ads;

use App\Observers\AdDetailCacheObserver;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * The rendered public ad detail, shared by every visitor who is not the
 * seller. Nothing viewer-specific may be stored here: `is_favorited` is laid
 * over the cached payload per request.
 *
 * Saves to the ad or its images drop the entry ({@see AdDetailCacheObserver});
 * the short stale-while-revalidate window only bounds how old the seller
 * card and the counters can get.
 */
class AdDetailCache
{
    /**
     * @param Closure(): array<string, mixed> $render
     * @return array<string, mixed>
     */
    public function remember(string $adId, Closure $render): array
    {
        $fresh = (int) config('qbazaar.ads.detail_cache_seconds');

        /** @var array<string, mixed> $payload */
        $payload = Cache::flexible($this->key($adId), [$fresh, $fresh * 2], $render);

        return $payload;
    }

    public function forget(string $adId): void
    {
        Cache::forget($this->key($adId));
    }

    private function key(string $adId): string
    {
        return 'ads.detail.v1.' . $adId;
    }
}
