<?php

declare(strict_types=1);

namespace App\Services\Ads\Views;

use App\Jobs\Ads\FlushAdViewCountsJob;

/**
 * Where accepted ad views are counted. Production buffers them in Redis and
 * {@see FlushAdViewCountsJob} writes them to `ads.views_count`
 * once a minute, so a popular ad is not one hot row taking a write per view.
 */
interface AdViewCounter
{
    public function record(string $adId): void;

    /** Moves buffered views into `ads.views_count`. */
    public function flush(): void;
}
