<?php

declare(strict_types=1);

namespace App\Services\Ads\Views;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

/**
 * Buffers views in one Redis hash (ad id => views) and drains it in a
 * single pass. Draining renames the hash first, so views recorded during a
 * flush land in a fresh hash; a flush that fails before its write keeps the
 * renamed hash, and the next run writes it before taking new views.
 *
 * Uses the queue connection on purpose: views must not be evicted the way
 * cache keys may be.
 */
class RedisAdViewCounter implements AdViewCounter
{
    private const BUFFER_KEY = 'ads:views:buffer';

    private const DRAINING_KEY = 'ads:views:draining';

    public function __construct(
        private readonly AdViewCountWriter $writer,
        private readonly string $connection,
    ) {}

    public function record(string $adId): void
    {
        $this->redis()->hincrby(self::BUFFER_KEY, $adId, 1);
    }

    public function flush(): void
    {
        $redis = $this->redis();

        if (! $redis->exists(self::DRAINING_KEY)) {
            if (! $redis->exists(self::BUFFER_KEY)) {
                return;
            }

            $redis->rename(self::BUFFER_KEY, self::DRAINING_KEY);
        }

        /** @var array<string, string> $buffered */
        $buffered = $redis->hgetall(self::DRAINING_KEY);

        $this->writer->add(array_map(intval(...), $buffered));

        $redis->del(self::DRAINING_KEY);
    }

    private function redis(): Connection
    {
        return Redis::connection($this->connection);
    }
}
