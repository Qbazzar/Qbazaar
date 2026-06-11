<?php

declare(strict_types=1);

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->token = fake()->regexify('[A-Za-z0-9_-]{152}');
});

it('registers a new device token', function (): void {
    Sanctum::actingAs($this->user, ['*']);

    $response = postJson('/api/v1/account/device-tokens', [
        'token' => $this->token,
        'platform' => 'web',
    ])->assertCreated();

    $this->assertDatabaseHas('device_tokens', [
        'token' => $this->token,
        'user_id' => $this->user->id,
        'platform' => 'web',
    ]);

    // The raw FCM token must never be echoed back to the client.
    expect($response->getContent())->not->toContain($this->token);
});

it('re-registers an existing token idempotently and refreshes last_used_at', function (): void {
    Sanctum::actingAs($this->user, ['*']);

    postJson('/api/v1/account/device-tokens', ['token' => $this->token])->assertCreated();

    /** @var DeviceToken $row */
    $row = DeviceToken::query()->where('token', $this->token)->firstOrFail();
    $firstUsedAt = $row->last_used_at;

    travel(1)->minutes();

    postJson('/api/v1/account/device-tokens', ['token' => $this->token])->assertOk();

    expect(DeviceToken::query()->where('token', $this->token)->count())->toBe(1);

    $row->refresh();
    expect($row->last_used_at?->greaterThan($firstUsedAt))->toBeTrue();
});

it('re-points the token at the new user when a different user registers it', function (): void {
    DeviceToken::factory()->create([
        'user_id' => $this->user->id,
        'token' => $this->token,
    ]);

    $newOwner = User::factory()->create();
    Sanctum::actingAs($newOwner, ['*']);

    postJson('/api/v1/account/device-tokens', ['token' => $this->token])->assertOk();

    expect(DeviceToken::query()->where('token', $this->token)->count())->toBe(1);

    $this->assertDatabaseHas('device_tokens', [
        'token' => $this->token,
        'user_id' => $newOwner->id,
    ]);
});

it('validates the register payload', function (): void {
    Sanctum::actingAs($this->user, ['*']);

    postJson('/api/v1/account/device-tokens', [])->assertStatus(422);

    postJson('/api/v1/account/device-tokens', [
        'token' => str_repeat('a', 513),
    ])->assertStatus(422);

    postJson('/api/v1/account/device-tokens', [
        'token' => $this->token,
        'platform' => 'blackberry',
    ])->assertStatus(422);
});

it('rejects unauthenticated requests', function (): void {
    postJson('/api/v1/account/device-tokens', ['token' => $this->token])->assertStatus(401);
    deleteJson('/api/v1/account/device-tokens', ['token' => $this->token])->assertStatus(401);
});

it('unregisters one of the caller\'s tokens', function (): void {
    DeviceToken::factory()->create([
        'user_id' => $this->user->id,
        'token' => $this->token,
    ]);

    Sanctum::actingAs($this->user, ['*']);

    deleteJson('/api/v1/account/device-tokens', ['token' => $this->token])
        ->assertNoContent();

    $this->assertDatabaseMissing('device_tokens', ['token' => $this->token]);
});

it('silently no-ops when deleting another user\'s token', function (): void {
    $owner = User::factory()->create();
    DeviceToken::factory()->create([
        'user_id' => $owner->id,
        'token' => $this->token,
    ]);

    Sanctum::actingAs($this->user, ['*']);

    // 204 either way — a foreign/unknown token must not be distinguishable.
    deleteJson('/api/v1/account/device-tokens', ['token' => $this->token])
        ->assertNoContent();

    $this->assertDatabaseHas('device_tokens', [
        'token' => $this->token,
        'user_id' => $owner->id,
    ]);
});
