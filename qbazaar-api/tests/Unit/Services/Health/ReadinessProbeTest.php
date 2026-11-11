<?php

declare(strict_types=1);

use App\Services\Admin\Dashboard\SchedulerHeartbeat;
use App\Services\Health\ReadinessProbe;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Redis\Connections\Connection as RedisConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

/**
 * @param array<string, mixed> $dependencies constructor arguments to replace
 * @return array<string, string>
 */
function readinessChecks(array $dependencies = []): array
{
    return app()->make(ReadinessProbe::class, $dependencies)->run()->toArray()['checks'];
}

/**
 * A Redis server that answers PING on the short-timeout `health` connection
 * only, or fails with `$failure` like a server that does not answer.
 */
function fakeQueueRedis(?Throwable $failure = null): RedisFactory
{
    return new class($failure) implements RedisFactory
    {
        public function __construct(private readonly ?Throwable $failure) {}

        public function connection($name = null)
        {
            if ($this->failure !== null) {
                throw $this->failure;
            }

            if ($name !== 'health') {
                throw new LogicException('The probe must use the health connection, which has short timeouts.');
            }

            return new class extends RedisConnection
            {
                /**
                 * @param array<int, string>|string $channels
                 */
                public function createSubscription($channels, Closure $callback, $method = 'subscribe'): void {}

                /**
                 * @param array<int, mixed> $parameters
                 */
                public function command($method, array $parameters = []): bool
                {
                    if ($method !== 'ping') {
                        throw new LogicException('The probe must only PING.');
                    }

                    return true;
                }
            };
        }
    };
}

/**
 * The production queue setup: Redis-backed, with Horizon reporting one master
 * supervisor in `$status`, or none when it is null.
 *
 * @return array<string, mixed>
 */
function redisQueueWithMaster(?string $status, ?Throwable $redisFailure = null): array
{
    config(['queue.default' => 'redis']);

    $masters = $status === null ? [] : [(object) ['status' => $status]];

    return [
        'redis' => fakeQueueRedis($redisFailure),
        'masters' => Mockery::mock(MasterSupervisorRepository::class, ['all' => $masters]),
    ];
}

it('passes in an environment without Redis queues or Meilisearch', function (): void {
    app(SchedulerHeartbeat::class)->beat();

    expect(readinessChecks())->toBe([
        'database' => 'ok',
        'redis' => 'disabled',
        'cache' => 'ok',
        'search' => 'disabled',
        'scheduler' => 'ok',
        'workers' => 'disabled',
    ]);
});

it('fails the database check when no connection can be opened', function (): void {
    $database = new class implements ConnectionResolverInterface
    {
        public function connection($name = null)
        {
            throw new PDOException('Connection refused');
        }

        public function getDefaultConnection()
        {
            return 'mysql';
        }

        public function setDefaultConnection($name): void {}
    };

    $report = app()->make(ReadinessProbe::class, ['database' => $database])->run();

    expect($report->toArray()['checks']['database'])->toBe('fail')
        ->and($report->isReady())->toBeFalse();
});

it('tells a stale scheduler from one that has not reported yet', function (): void {
    expect(readinessChecks()['scheduler'])->toBe('unknown');

    Cache::put(SchedulerHeartbeat::CACHE_KEY, now()->subMinutes(10)->getTimestamp());

    expect(readinessChecks()['scheduler'])->toBe('stale');
});

it('asks Meilisearch for its health when it is the search driver', function (int $status, string $expected): void {
    config(['scout.driver' => 'meilisearch', 'scout.meilisearch.host' => 'http://meili.test/']);
    Http::fake(['meili.test/health' => Http::response(['status' => 'available'], $status)]);

    expect(readinessChecks()['search'])->toBe($expected);
})->with([
    'available' => [200, 'ok'],
    'unavailable' => [503, 'fail'],
]);

it('needs a running Horizon master supervisor', function (?string $status, string $expected): void {
    $checks = readinessChecks(redisQueueWithMaster($status));

    expect($checks['redis'])->toBe('ok')
        ->and($checks['workers'])->toBe($expected);
})->with([
    'running' => ['running', 'ok'],
    'paused' => ['paused', 'fail'],
    'none' => [null, 'fail'],
]);

it('fails the Redis check when the queue server does not answer', function (): void {
    $report = app()->make(ReadinessProbe::class, redisQueueWithMaster(null, new RuntimeException('Connection refused')))->run();

    expect($report->toArray()['checks']['redis'])->toBe('fail')
        ->and($report->isReady())->toBeFalse();
});
