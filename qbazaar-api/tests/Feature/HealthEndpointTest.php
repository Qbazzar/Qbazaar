<?php

declare(strict_types=1);

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

it('returns a healthy status from /api/v1/health', function (): void {
    getJson('/api/v1/health')
        ->assertOk()
        ->assertJson(fn ($json) => $json
            ->where('success', true)
            ->has('data.status')
            ->has('data.version')
            ->has('data.timestamp')
            ->etc()
        );
});

it('returns 200 on the framework /up health check', function (): void {
    get('/up')->assertOk();
});
