<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Jobs\Catalog\WarmCatalogCacheJob;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Cache entries that {@see WarmCatalogCacheJob} rebuilds on
 * a schedule. Requests only read them; the TTL is a safety net that bounds
 * staleness if the scheduler stops, not the refresh rate.
 *
 * On a miss (first deploy, evicted key, taxonomy change) exactly one request
 * rebuilds the entry while concurrent ones wait for it, so a cold cache never
 * turns into a burst of identical heavy queries.
 */
class WarmedCache
{
    private const BUILD_LOCK_SECONDS = 60;

    private const BUILD_WAIT_SECONDS = 10;

    /**
     * @template TValue
     *
     * @param Closure(): TValue $build
     * @return TValue
     */
    public function get(string $key, int $ttlSeconds, Closure $build): mixed
    {
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        try {
            return Cache::lock($key . ':build', self::BUILD_LOCK_SECONDS)->block(
                self::BUILD_WAIT_SECONDS,
                fn (): mixed => Cache::get($key) ?? $this->put($key, $ttlSeconds, $build),
            );
        } catch (LockTimeoutException) {
            // The rebuild holding the lock is unusually slow; answering from
            // the database beats failing the request.
            return $build();
        }
    }

    /**
     * @template TValue
     *
     * @param Closure(): TValue $build
     * @return TValue
     */
    public function put(string $key, int $ttlSeconds, Closure $build): mixed
    {
        $value = $build();

        Cache::put($key, $value, $ttlSeconds);

        return $value;
    }
}
