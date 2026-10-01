<?php

declare(strict_types=1);

namespace App\Jobs\Ads;

use App\Services\Ads\Views\AdViewCounter;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Writes the views buffered since the last run to `ads.views_count`.
 * Scheduled every minute; a failed run leaves the drained batch in Redis
 * for the retry or the next run.
 */
class FlushAdViewCountsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 20];

    public int $timeout = 60;

    public int $uniqueFor = 120;

    public function __construct()
    {
        $this->onQueue('low');
    }

    public function handle(AdViewCounter $views): void
    {
        $views->flush();
    }
}
