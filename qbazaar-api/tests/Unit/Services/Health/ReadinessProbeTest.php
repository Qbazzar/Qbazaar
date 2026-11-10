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
 * The production queue setup: Redis-backed, with Redis answering the ping
 * and Horizon reporting the given master supervisors.
 *
 * @param list<object> $masters
 * @return array<string, mixed>
 */
function redisQueueWithMasters(array $masters): array
{
    config(['queue.default' => 'redis']);

    $connection = Mockery::mock(RedisConnection::class);
    $connection->shouldReceive('command')->with('ping')->andReturn(true);
    $redis = Mockery::mock(RedisFactory::class);
    $redis->shouldReceive('connection')->with('health')->andReturn($connection);

    $repository = Mockery::mock(MasterSupervisorRepository::class);
    $repository->shouldReceive('all')->andReturn($masters);

    return ['redis' => $redis, 'masters' => $repository];
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
    $database = Mockery::mock(ConnectionResolverInterface::class);
    $database->shouldReceive('connection')->andThrow(new PDOException('Connection refused'));

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

it('needs a running Horizon master supervisor', function (array $masters, string $expected): void {
    $checks = readinessChecks(redisQueueWithMasters($masters));

    expect($checks['redis'])->toBe('ok')
        ->and($checks['workers'])->toBe($expected);
})->with([
    'running' => [[(object) ['status' => 'running']], 'ok'],
    'paused' => [[(object) ['status' => 'paused']], 'fail'],
    'none' => [[], 'fail'],
]);

it('fails the Redis check when the queue server does not answer', function (): void {
    $redis = Mockery::mock(RedisFactory::class);
    $redis->shouldReceive('connection')->andThrow(new RuntimeException('Connection refused'));

    $report = app()->make(ReadinessProbe::class, ['redis' => $redis] + redisQueueWithMasters([]))->run();

    expect($report->toArray()['checks']['redis'])->toBe('fail')
        ->and($report->isReady())->toBeFalse();
});
