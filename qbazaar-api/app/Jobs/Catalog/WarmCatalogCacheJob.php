<?php

declare(strict_types=1);

namespace App\Jobs\Catalog;

use App\Enums\QueueName;
use App\Services\Catalog\CategoryAdCounts;
use App\Services\Catalog\HomeFeedCache;
use App\Services\Catalog\LocationAdCounts;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Rebuilds the category and place counters and the home feed, so requests
 * never pay for them. Scheduled every `qbazaar.catalog.warm_every_minutes`.
 *
 * The counters go first because the home feed embeds both. Each rebuild
 * overwrites its entry in one write, so a retry or an overlapping run only
 * repeats the work; uniqueness keeps a slow queue from stacking copies.
 */
class WarmCatalogCacheJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30];

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function __construct()
    {
        $this->onQueue(QueueName::LOW);
    }

    public function handle(CategoryAdCounts $categoryCounts, LocationAdCounts $placeCounts, HomeFeedCache $homeFeed): void
    {
        $categoryCounts->refresh();
        $placeCounts->refresh();
        $homeFeed->refresh();
    }
}
