<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\Favorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

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

function favoritesCount(Ad $ad): int
{
    return (int) Ad::query()->whereKey($ad->id)->value('favorites_count');
}

it('adds a favourite once no matter how often PUT is repeated', function (): void {
    Sanctum::actingAs($this->user, ['*']);

    putJson("/api/v1/ads/{$this->ad->id}/favorite")
        ->assertOk()
        ->assertJsonPath('data.favorited', true)
        ->assertJsonPath('data.count', 1);

    putJson("/api/v1/ads/{$this->ad->id}/favorite")
        ->assertOk()
        ->assertJsonPath('data.favorited', true)
        ->assertJsonPath('data.count', 1);

    expect(Favorite::query()->where('user_id', $this->user->id)->count())->toBe(1)
        ->and(favoritesCount($this->ad))->toBe(1);
});

it('removes a favourite idempotently with DELETE', function (): void {
    Sanctum::actingAs($this->user, ['*']);

    putJson("/api/v1/ads/{$this->ad->id}/favorite")->assertOk();

    deleteJson("/api/v1/ads/{$this->ad->id}/favorite")
        ->assertOk()
        ->assertJsonPath('data.favorited', false)
        ->assertJsonPath('data.count', 0);

    deleteJson("/api/v1/ads/{$this->ad->id}/favorite")
        ->assertOk()
        ->assertJsonPath('data.favorited', false)
        ->assertJsonPath('data.count', 0);

    expect(favoritesCount($this->ad))->toBe(0);
});

it('keeps the toggle working alongside PUT and DELETE', function (): void {
    Sanctum::actingAs($this->user, ['*']);

    putJson("/api/v1/ads/{$this->ad->id}/favorite")->assertOk();

    postJson("/api/v1/ads/{$this->ad->id}/favorite")
        ->assertOk()
        ->assertJsonPath('data.favorited', false);

    postJson("/api/v1/ads/{$this->ad->id}/favorite")
        ->assertOk()
        ->assertJsonPath('data.favorited', true)
        ->assertJsonPath('data.count', 1);
});

it('lists only the caller favourite ids, newest first', function (): void {
    $older = $this->makeAd(User::factory()->create(), ['status' => 'active']);
    Favorite::query()->create(['user_id' => $this->user->id, 'ad_id' => $older->id, 'created_at' => now()->subDay()]);
    Favorite::query()->create(['user_id' => $this->user->id, 'ad_id' => $this->ad->id, 'created_at' => now()]);
    Favorite::query()->create(['user_id' => User::factory()->create()->id, 'ad_id' => $older->id, 'created_at' => now()]);

    Sanctum::actingAs($this->user, ['*']);

    getJson('/api/v1/account/favorites/ids')
        ->assertOk()
        ->assertJsonPath('data.ids', [$this->ad->id, $older->id]);
});

it('refuses new favourites past the per-user cap', function (): void {
    config(['qbazaar.favorites.max_per_user' => 1]);
    $first = $this->makeAd(User::factory()->create(), ['status' => 'active']);
    Favorite::query()->create(['user_id' => $this->user->id, 'ad_id' => $first->id, 'created_at' => now()]);

    Sanctum::actingAs($this->user, ['*']);

    putJson("/api/v1/ads/{$this->ad->id}/favorite")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'FAV_001');

    putJson("/api/v1/ads/{$first->id}/favorite")->assertOk();
});

it('returns AD_NOT_FOUND for unknown ads and 401 for guests', function (): void {
    putJson("/api/v1/ads/{$this->ad->id}/favorite")->assertUnauthorized();
    getJson('/api/v1/account/favorites/ids')->assertUnauthorized();

    Sanctum::actingAs($this->user, ['*']);

    putJson('/api/v1/ads/01HZZZZZZZZZZZZZZZZZZZZZZZ/favorite')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'AD_001');
});
