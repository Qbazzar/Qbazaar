<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Ad;
use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Meilisearch\Endpoints\Indexes;
use Throwable;

/**
 * Test helper for Meilisearch-backed search assertions.
 *
 * Meilisearch indexing is async — `searchable()` returns immediately while
 * the document is queued. Tests need to wait for the enqueued tasks to
 * finish before searching, otherwise the assertions race the indexer.
 *
 * This trait centralises that wait + a tiny "flush index" helper so each
 * test starts from a clean slate.
 */
trait InteractsWithMeilisearch
{
    protected function meilisearchClient(): Client
    {
        /** @var Client $client */
        $client = app(Client::class);

        return $client;
    }

    /**
     * Wait until every queued indexing task for the ads index has settled.
     * Falls back to a short sleep if the SDK call fails (e.g. Meili is down
     * locally) so a test that doesn't actually depend on the index passing
     * doesn't get stuck.
     */
    protected function waitForMeilisearch(): void
    {
        try {
            $index = $this->adIndex();
            $tasks = $this->meilisearchClient()->getTasks(
                (new TasksQuery)
                    ->setIndexUids([$index->getUid()])
                    ->setLimit(20),
            );

            foreach ($tasks->getResults() as $task) {
                if (in_array($task['status'], ['enqueued', 'processing'], true)) {
                    $this->meilisearchClient()->waitForTask($task['uid']);
                }
            }
        } catch (Throwable) {
            usleep(500_000); // 0.5s fallback
        }
    }

    /**
     * Truncate the ads index so each test starts from zero, and (re)install the
     * configured index settings. Settings come first because `updateSettings`
     * creates the index when it's missing and installs the filterable / sortable
     * attributes every facet / range / sort query depends on — without them
     * Meilisearch 400s with "Attribute `status` is not filterable".
     */
    protected function flushAdsIndex(): void
    {
        try {
            $this->syncAdsIndexSettings();
            $this->adIndex()->deleteAllDocuments();
            $this->waitForMeilisearch();
        } catch (Throwable) {
            // Index didn't exist yet — first `searchable()` will create it.
        }
    }

    /**
     * Apply config('scout.meilisearch.index-settings.ads_index') to the prefixed
     * test index. Scout's `scout:sync-index-settings` only targets the index for
     * the active SCOUT_PREFIX, so the `test_`-prefixed index used under PHPUnit
     * never receives settings unless we push them here.
     */
    protected function syncAdsIndexSettings(): void
    {
        /** @var array<string, mixed>|null $settings */
        $settings = config('scout.meilisearch.index-settings.ads_index');

        if (! is_array($settings)) {
            return;
        }

        $task = $this->adIndex()->updateSettings($settings);
        $this->meilisearchClient()->waitForTask($task['taskUid']);
    }

    private function adIndex(): Indexes
    {
        $name = (new Ad)->searchableAs();

        return $this->meilisearchClient()->index($name);
    }
}
