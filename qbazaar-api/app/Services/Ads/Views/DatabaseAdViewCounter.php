<?php

declare(strict_types=1);

namespace App\Services\Ads\Views;

use App\Models\Ad;

/**
 * Writes every view straight to the ad row. For environments without Redis
 * (local, tests); production uses {@see RedisAdViewCounter}.
 */
class DatabaseAdViewCounter implements AdViewCounter
{
    public function record(string $adId): void
    {
        Ad::query()->whereKey($adId)->increment('views_count');
    }

    public function flush(): void {}
}
