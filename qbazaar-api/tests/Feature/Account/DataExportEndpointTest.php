<?php

declare(strict_types=1);

use App\Jobs\ExportUserDataJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);
    Bus::fake();
});

it('queues an ExportUserDataJob and returns 202 with queue metadata', function (): void {
    postJson('/api/v1/account/data-export-request', [])
        ->assertStatus(202)
        ->assertJson(
            fn ($json) => $json
                ->where('success', true)
                ->where('data.status', 'queued')
                ->where('data.eta_minutes', 5)
                ->has('data.requested_at')
                ->etc(),
        );

    Bus::assertDispatched(ExportUserDataJob::class, function (ExportUserDataJob $job): bool {
        return $job->userId === $this->user->id;
    });
});

it('throttles a second request inside 24h to 429 RATE_LIMIT_EXCEEDED', function (): void {
    postJson('/api/v1/account/data-export-request', [])->assertStatus(202);

    postJson('/api/v1/account/data-export-request', [])
        ->assertStatus(429)
        ->assertJson(
            fn ($json) => $json
                ->where('error.code', 'RATE_LIMIT_EXCEEDED')
                ->etc(),
        );

    Bus::assertDispatchedTimes(ExportUserDataJob::class, 1);
});

it('rejects unauthenticated requests', function (): void {
    $this->refreshApplication();

    postJson('/api/v1/account/data-export-request', [])->assertStatus(401);
});
