<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard\Collectors;

use App\Models\Ad;
use App\Services\Admin\Dashboard\SchedulerHeartbeat;
use App\Services\Admin\Dashboard\StorageProbe;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Cheap reachability probes for the services the platform depends on. Every
 * probe has a short timeout and reports `unavailable` instead of throwing, so
 * a dead dependency shows up on the dashboard rather than breaking it.
 */
final class SystemHealth
{
    private const int HTTP_TIMEOUT_SECONDS = 2;

    private const int HTTP_CONNECT_TIMEOUT_SECONDS = 1;

    /** Same Redis lists as `redis`, reached through a connection with short timeouts (see config/database.php). */
    private const string PROBE_CONNECTION = 'redis-health';

    public function __construct(
        private readonly SchedulerHeartbeat $heartbeat,
        private readonly AttentionQueues $queues,
        private readonly StorageProbe $storage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function check(CarbonImmutable $now): array
    {
        return [
            'checked_at' => $now->toIso8601String(),
            'queues' => $this->queueSizes(),
            'failed_jobs' => $this->queues->failedJobs()['count'],
            'search' => $this->search(),
            'scheduler' => $this->scheduler($now),
            'storage' => $this->storage(),
        ];
    }

    /**
     * Waiting jobs per queue, listed the way Horizon's supervisors name them
     * (`connection:queue`).
     *
     * @return array{status: string, sizes: array<string, int>}
     */
    private function queueSizes(): array
    {
        $default = (string) config('queue.default');

        if (config("queue.connections.{$default}.driver") !== 'redis') {
            return ['status' => 'disabled', 'sizes' => []];
        }

        try {
            $sizes = [];
            foreach (array_keys((array) config('horizon.waits')) as $pair) {
                [, $queue] = explode(':', (string) $pair, 2);
                $sizes[$queue] = Queue::connection(self::PROBE_CONNECTION)->size($queue);
            }

            return ['status' => 'ok', 'sizes' => $sizes];
        } catch (Throwable) {
            return ['status' => 'unavailable', 'sizes' => []];
        }
    }

    /**
     * @return array{status: string, documents: int|null}
     */
    private function search(): array
    {
        if (config('scout.driver') !== 'meilisearch') {
            return ['status' => 'disabled', 'documents' => null];
        }

        try {
            $host = rtrim((string) config('scout.meilisearch.host'), '/');
            $client = Http::connectTimeout(self::HTTP_CONNECT_TIMEOUT_SECONDS)->timeout(self::HTTP_TIMEOUT_SECONDS)->withToken((string) config('scout.meilisearch.key'));
            $client->get("{$host}/health")->throw();
            $stats = $client->get("{$host}/indexes/" . (new Ad)->searchableAs() . '/stats');

            return ['status' => 'ok', 'documents' => $stats->successful() ? (int) $stats->json('numberOfDocuments') : null];
        } catch (Throwable) {
            return ['status' => 'unavailable', 'documents' => null];
        }
    }

    /**
     * @return array{status: string, last_run: string|null}
     */
    private function scheduler(CarbonImmutable $now): array
    {
        $last = $this->heartbeat->lastRun();
        $fresh = $last !== null && $last->diffInSeconds($now, true) <= SchedulerHeartbeat::STALE_AFTER_SECONDS;

        return ['status' => $last === null ? 'unknown' : ($fresh ? 'ok' : 'stale'), 'last_run' => $last?->toIso8601String()];
    }

    /**
     * Asks the media disk about a file that does not exist: Flysystem answers
     * `false` when the storage is reachable and throws when it is not.
     *
     * @return array{status: string, disk: string}
     */
    private function storage(): array
    {
        $disk = (string) config('media-library.disk_name', config('filesystems.default'));

        try {
            $this->storage->check($disk);

            return ['status' => 'ok', 'disk' => $disk];
        } catch (Throwable) {
            return ['status' => 'unavailable', 'disk' => $disk];
        }
    }
}
