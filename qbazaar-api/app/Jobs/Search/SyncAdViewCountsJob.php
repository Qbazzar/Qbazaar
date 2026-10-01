<?php

declare(strict_types=1);

namespace App\Jobs\Search;

use App\Enums\QueueName;
use App\Models\Ad;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Meilisearch\Client;

/**
 * Pushes fresh view counters into the search index so `most_viewed` sorts
 * on current numbers.
 *
 * A view bumps `views_count` with a query-builder increment, which skips
 * model events (and so Scout) but does touch `updated_at`. Re-sending every
 * public ad touched in the last two intervals sends each changed counter at
 * least once, even after a skipped run; the update is a partial document
 * write of absolute values, so overlap and retries are harmless.
 */
class SyncAdViewCountsJob implements ShouldQueue
{
    use Queueable;

    private const CHUNK = 500;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue(QueueName::LOW);
    }

    public function handle(Client $meilisearch): void
    {
        if (config('scout.driver') !== 'meilisearch') {
            return;
        }

        $index = $meilisearch->index((new Ad)->searchableAs());
        $since = now()->subMinutes(2 * (int) config('qbazaar.search.views_sync_minutes'));

        Ad::query()
            ->publiclyListed()
            ->where('updated_at', '>=', $since)
            ->select(['id', 'views_count'])
            ->chunkById(self::CHUNK, function (Collection $ads) use ($index): void {
                $index->updateDocuments(
                    $ads->map(fn (Ad $ad): array => ['id' => $ad->id, 'views_count' => (int) $ad->views_count])->all(),
                    'id',
                );
            });
    }
}
