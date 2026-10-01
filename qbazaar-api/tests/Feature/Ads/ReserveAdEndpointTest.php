<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\User;
use App\Services\Ads\AdLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();

    $this->activeAd = fn (): Ad => $this->makeAd($this->seller, [
        'status' => AdStatus::ACTIVE->value,
        'published_at' => now()->subDay(),
        'expires_at' => now()->addDays(29),
    ]);
});

it('reserves a live ad and keeps it active', function (): void {
    Sanctum::actingAs($this->seller, ['*']);
    $ad = ($this->activeAd)();

    postJson("/api/v1/ads/{$ad->id}/reserve")
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::ACTIVE->value)
        ->assertJsonPath('data.is_reserved', true);

    expect($ad->fresh()->reserved_at)->not->toBeNull();
});

it('keeps the first reservation time when reserving twice', function (): void {
    Sanctum::actingAs($this->seller, ['*']);
    $ad = ($this->activeAd)();

    $this->travelTo(now()->subHour(), fn () => postJson("/api/v1/ads/{$ad->id}/reserve")->assertOk());
    $first = $ad->fresh()->reserved_at;

    postJson("/api/v1/ads/{$ad->id}/reserve")->assertOk();

    expect($ad->fresh()->reserved_at->equalTo($first))->toBeTrue();
});

it('releases a reservation and is idempotent', function (): void {
    Sanctum::actingAs($this->seller, ['*']);
    $ad = ($this->activeAd)();
    $ad->forceFill(['reserved_at' => now()])->save();

    deleteJson("/api/v1/ads/{$ad->id}/reserve")
        ->assertOk()
        ->assertJsonPath('data.is_reserved', false);

    deleteJson("/api/v1/ads/{$ad->id}/reserve")->assertOk();
});

it('refuses to reserve an ad that is not live', function (string $status): void {
    Sanctum::actingAs($this->seller, ['*']);
    $ad = $this->makeAd($this->seller, ['status' => $status]);

    postJson("/api/v1/ads/{$ad->id}/reserve")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'AD_002');

    expect($ad->fresh()->reserved_at)->toBeNull();
})->with(['draft', 'pending', 'sold', 'expired']);

it('lets only the seller reserve', function (): void {
    $ad = ($this->activeAd)();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    postJson("/api/v1/ads/{$ad->id}/reserve")->assertForbidden();
    deleteJson("/api/v1/ads/{$ad->id}/reserve")->assertForbidden();
});

it('requires authentication', function (): void {
    $ad = ($this->activeAd)();

    postJson("/api/v1/ads/{$ad->id}/reserve")->assertUnauthorized();
});

it('returns AD_001 for an unknown ad', function (): void {
    Sanctum::actingAs($this->seller, ['*']);

    postJson('/api/v1/ads/01HZZZZZZZZZZZZZZZZZZZZZZZ/reserve')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'AD_001');
});

it('shows a reserved ad in the public listings', function (): void {
    $ad = ($this->activeAd)();
    $ad->forceFill(['reserved_at' => now()])->save();

    getJson('/api/v1/ads')
        ->assertOk()
        ->assertJsonPath('data.0.id', $ad->id)
        ->assertJsonPath('data.0.is_reserved', true);

    expect($ad->fresh()->toSearchableArray()['is_reserved'])->toBeTrue();
});

it('releases the reservation when the ad leaves the listings', function (): void {
    $ad = ($this->activeAd)();
    $ad->forceFill(['reserved_at' => now()])->save();

    app(AdLifecycleService::class)->markSold($ad);

    expect($ad->fresh())
        ->status->toBe(AdStatus::SOLD)
        ->reserved_at->toBeNull();
});
