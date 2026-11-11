<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Content;

use App\Models\Ad;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps the Meilisearch index in step with demo content that was changed
 * without model events. A search server that is down only costs a log line;
 * with any other Scout driver there is nothing to do.
 */
final class DemoSearchSync
{
    private const int CHUNK = 200;

    /**
     * @param list<string> $adIds
     * @return int ads handed to the search index
     */
    public function reindex(array $adIds): int
    {
        $unique = array_values(array_unique($adIds));

        if (! $this->enabled() || $unique === []) {
            return 0;
        }

        try {
            foreach (array_chunk($unique, self::CHUNK) as $chunk) {
                Ad::query()->whereIn('id', $chunk)->get()->searchable();
            }
        } catch (Throwable $failure) {
            Log::warning('Demo content could not update the search index.', ['error' => $failure->getMessage()]);

            return 0;
        }

        return count($unique);
    }

    /**
     * @param Collection<int, Ad> $ads
     */
    public function forget(Collection $ads): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            $ads->unsearchable();
        } catch (Throwable $failure) {
            Log::warning('Demo content could not be removed from the search index.', ['error' => $failure->getMessage()]);
        }
    }

    private function enabled(): bool
    {
        return config('scout.driver') === 'meilisearch';
    }
}
