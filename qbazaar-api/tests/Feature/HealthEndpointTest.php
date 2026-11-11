<?php

declare(strict_types=1);

use App\Services\Admin\Dashboard\SchedulerHeartbeat;
use App\Services\Health\CheckStatus;
use App\Services\Health\ReadinessProbe;
use App\Services\Health\ReadinessReport;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

use Psr\Log\LoggerInterface;

it('returns a healthy status from /api/v1/health', function (): void {
    getJson('/api/v1/health')
        ->assertOk()
        ->assertJson(
            fn ($json) => $json
                ->where('success', true)
                ->has('data.status')
                ->has('data.version')
                ->has('data.timestamp')
                ->etc(),
        );
});

it('returns 200 on the framework /up health check', function (): void {
    get('/up')->assertOk();
});

it('reports readiness as a bare, uncached body', function (): void {
    app(SchedulerHeartbeat::class)->beat();

    $response = getJson('/api/v1/health/ready')->assertOk();

    expect($response->json())->toBe([
        'status' => 'ok',
        'checks' => [
            'database' => 'ok',
            'redis' => 'disabled',
            'cache' => 'ok',
            'search' => 'disabled',
            'scheduler' => 'ok',
            'workers' => 'disabled',
        ],
    ])
        ->and((string) $response->headers->get('Cache-Control'))->toContain('no-store');
});

it('answers 503 when a critical dependency is down', function (): void {
    app()->instance(ReadinessProbe::class, Mockery::mock(ReadinessProbe::class, [
        'run' => new ReadinessReport(['database' => CheckStatus::FAIL, 'search' => CheckStatus::OK], ['database']),
    ]));

    getJson('/api/v1/health/ready')
        ->assertStatus(503)
        ->assertExactJson(['status' => 'down', 'checks' => ['database' => 'fail', 'search' => 'ok']]);
});

it('still reports 503 with cache=fail when the cache store is down', function (): void {
    Cache::extend('broken', fn () => new Repository(new class implements Store
    {
        public function get($key): never
        {
            throw new RuntimeException('cache host secret.internal:6379 refused');
        }

        public function many(array $keys): never
        {
            throw new RuntimeException('down');
        }

        public function put($key, $value, $seconds): never
        {
            throw new RuntimeException('down');
        }

        public function putMany(array $values, $seconds): never
        {
            throw new RuntimeException('down');
        }

        public function increment($key, $value = 1): never
        {
            throw new RuntimeException('down');
        }

        public function decrement($key, $value = 1): never
        {
            throw new RuntimeException('down');
        }

        public function forever($key, $value): never
        {
            throw new RuntimeException('down');
        }

        public function forget($key): never
        {
            throw new RuntimeException('down');
        }

        public function flush(): never
        {
            throw new RuntimeException('down');
        }

        public function getPrefix(): string
        {
            return '';
        }
    }));
    config([
        'cache.stores.broken' => ['driver' => 'broken'],
        'cache.default' => 'broken',
        'cache.limiter' => 'broken',
    ]);
    app('cache')->forgetDriver('broken');
    app()->forgetInstance('cache.store');
    app()->forgetInstance(RateLimiter::class);
    app()->instance(LoggerInterface::class, Mockery::spy(LoggerInterface::class));

    $response = getJson('/api/v1/health/ready')->assertStatus(503);

    expect($response->json('status'))->toBe('down')
        ->and($response->json('checks.cache'))->toBe('fail')
        ->and(array_keys($response->json('checks')))->toBe(['database', 'redis', 'cache', 'search', 'scheduler', 'workers'])
        ->and($response->getContent())->not->toContain('secret.internal')
        ->and($response->getContent())->not->toContain('refused');
});
