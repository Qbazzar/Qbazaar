<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\Favorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->user = User::factory()->create();
    $this->ad = $this->makeAd(User::factory()->create(), [
        'status' => 'active',
        'published_at' => now(),
        'favorites_count' => 0,
    ]);
});

it('toggles a favourite on first call and increments the count', function (): void {
    Sanctum::actingAs($this->user, ['*']);

    $response = postJson("/api/v1/ads/{$this->ad->id}/favorite", [], ['Accept' => 'application/json'])
        ->assertOk();

    expect($response->json('data.favorited'))->toBeTrue()
        ->and($response->json('data.count'))->toBe(1);

    expect(Favorite::query()->where('user_id', $this->user->id)->where('ad_id', $this->ad->id)->exists())->toBeTrue()
        ->and((int) Ad::query()->where('id', $this->ad->id)->value('favorites_count'))->toBe(1);
});

it('toggles the favourite off on the second call and decrements the count', function (): void {
    Sanctum::actingAs($this->user, ['*']);

    postJson("/api/v1/ads/{$this->ad->id}/favorite", [], ['Accept' => 'application/json'])->assertOk();

    $response = postJson("/api/v1/ads/{$this->ad->id}/favorite", [], ['Accept' => 'application/json'])
        ->assertOk();

    expect($response->json('data.favorited'))->toBeFalse()
        ->and($response->json('data.count'))->toBe(0);

    expect(Favorite::query()->where('user_id', $this->user->id)->where('ad_id', $this->ad->id)->exists())->toBeFalse()
        ->and((int) Ad::query()->where('id', $this->ad->id)->value('favorites_count'))->toBe(0);
});

it('keeps separate counts per user', function (): void {
    $other = User::factory()->create();

    Sanctum::actingAs($this->user, ['*']);
    postJson("/api/v1/ads/{$this->ad->id}/favorite", [], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.count', 1);

    Sanctum::actingAs($other, ['*']);
    $response = postJson("/api/v1/ads/{$this->ad->id}/favorite", [], ['Accept' => 'application/json'])
        ->assertOk();

    expect($response->json('data.favorited'))->toBeTrue()
        ->and($response->json('data.count'))->toBe(2);
});

it('returns AD_NOT_FOUND when the ad does not exist', function (): void {
    Sanctum::actingAs($this->user, ['*']);

    postJson('/api/v1/ads/01HZZZZZZZZZZZZZZZZZZZZZZZ/favorite', [], ['Accept' => 'application/json'])
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'AD_001');
});
