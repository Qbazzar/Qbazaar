<?php

declare(strict_types=1);

use App\Services\Admin\Dashboard\SchedulerHeartbeat;
use App\Services\Health\CheckStatus;
use App\Services\Health\ReadinessProbe;
use App\Services\Health\ReadinessReport;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

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
