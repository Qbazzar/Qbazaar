<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Services\Admin\Dashboard\SchedulerHeartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Answers "can the API serve requests?" for uptime monitors
 * (GET /api/v1/health/ready). Each check is one cheap round trip with a
 * short timeout and never throws, so a dead dependency shows up as a 503
 * rather than a hung request. The admin home's SystemHealth collector is the
 * detailed view (queue sizes, failed jobs, storage).
 *
 * Not final so feature tests can stand in a fake report.
 */
class ReadinessProbe
{
    /** Without these the API cannot answer: one failing means 503. */
    private const array CRITICAL = ['database', 'redis', 'cache'];

    /** The `default` Redis server with short timeouts and no retries (config/database.php). */
    private const string REDIS_PROBE_CONNECTION = 'health';

    private const string CACHE_PROBE_KEY = 'health:ready:probe';

    private const int HTTP_TIMEOUT_SECONDS = 1;

    private const int HTTP_CONNECT_TIMEOUT_SECONDS = 1;

    public function __construct(
        private readonly ConnectionResolverInterface $database,
        private readonly RedisFactory $redis,
        private readonly CacheFactory $cache,
        private readonly HttpFactory $http,
        private readonly SchedulerHeartbeat $heartbeat,
        private readonly MasterSupervisorRepository $masters,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function run(): ReadinessReport
    {
        return new ReadinessReport([
            'database' => $this->attempt('database', fn () => $this->database->connection()->select('select 1')),
            'redis' => $this->usesRedisQueue()
                ? $this->attempt('redis', fn () => $this->redis->connection(self::REDIS_PROBE_CONNECTION)->command('ping'))
                : CheckStatus::DISABLED,
            'cache' => $this->attempt('cache', fn () => $this->cache->store()->get(self::CACHE_PROBE_KEY)),
            'search' => $this->search(),
            'scheduler' => $this->scheduler(),
            'workers' => $this->workers(),
        ], self::CRITICAL);
    }

    private function search(): CheckStatus
    {
        if ($this->config->get('scout.driver') !== 'meilisearch') {
            return CheckStatus::DISABLED;
        }

        $host = rtrim((string) $this->config->get('scout.meilisearch.host'), '/');

        return $this->attempt('search', fn () => $this->http
            ->connectTimeout(self::HTTP_CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::HTTP_TIMEOUT_SECONDS)
            ->get("{$host}/health")
            ->throw());
    }

    private function scheduler(): CheckStatus
    {
        try {
            $lastRun = $this->heartbeat->lastRun();
        } catch (Throwable $e) {
            return $this->failed('scheduler', $e);
        }

        return match (true) {
            $lastRun === null => CheckStatus::UNKNOWN,
            $this->heartbeat->isFresh($lastRun, CarbonImmutable::now()) => CheckStatus::OK,
            default => CheckStatus::STALE,
        };
    }

    /**
     * Horizon lists a master supervisor only while it has reported in the
     * last few seconds, so an empty or all-paused list means no worker runs.
     */
    private function workers(): CheckStatus
    {
        if (! $this->usesRedisQueue()) {
            return CheckStatus::DISABLED;
        }

        try {
            foreach ($this->masters->all() as $master) {
                if (is_object($master) && ($master->status ?? null) === 'running') {
                    return CheckStatus::OK;
                }
            }

            return CheckStatus::FAIL;
        } catch (Throwable $e) {
            return $this->failed('workers', $e);
        }
    }

    private function usesRedisQueue(): bool
    {
        $connection = (string) $this->config->get('queue.default');

        return $this->config->get("queue.connections.{$connection}.driver") === 'redis';
    }

    /**
     * @param callable(): mixed $check
     */
    private function attempt(string $name, callable $check): CheckStatus
    {
        try {
            $check();

            return CheckStatus::OK;
        } catch (Throwable $e) {
            return $this->failed($name, $e);
        }
    }

    /**
     * Logged rather than reported: a monitor polls every minute, and an
     * outage would otherwise flood the error tracker with the same event.
     */
    private function failed(string $name, Throwable $e): CheckStatus
    {
        $this->logger->warning('Readiness check failed', ['check' => $name, 'exception' => $e::class, 'message' => $e->getMessage()]);

        return CheckStatus::FAIL;
    }
}
