<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * A timestamp the scheduler writes every minute, so the dashboard can tell
 * "cron stopped" from "nothing was due". One cache write per minute.
 */
class SchedulerHeartbeat
{
    public const string CACHE_KEY = 'admin:scheduler:heartbeat';

    /** Missing for this long means cron is not running. */
    public const int STALE_AFTER_SECONDS = 180;

    public function __construct(
        private readonly Cache $cache,
    ) {}

    public function beat(): void
    {
        $this->cache->put(self::CACHE_KEY, CarbonImmutable::now()->getTimestamp(), 86400);
    }

    public function lastRun(): ?CarbonImmutable
    {
        $timestamp = $this->cache->get(self::CACHE_KEY);

        return $timestamp === null ? null : CarbonImmutable::createFromTimestamp((int) $timestamp);
    }
}
