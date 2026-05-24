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
     * Truncate the ads index so each test starts from zero. We delete the
     * index entirely + recreate via Scout's sync command so settings stay
     * in step.
     */
    protected function flushAdsIndex(): void
    {
        try {
            $index = $this->adIndex();
            $index->deleteAllDocuments();
            $this->waitForMeilisearch();
        } catch (Throwable) {
            // Index didn't exist yet — first `searchable()` will create it.
        }
    }

    private function adIndex(): Indexes
    {
        $name = (new Ad)->searchableAs();

        return $this->meilisearchClient()->index($name);
    }
}
