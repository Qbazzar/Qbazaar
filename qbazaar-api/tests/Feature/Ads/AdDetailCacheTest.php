<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Favorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    Cache::flush();
    $this->seller = User::factory()->create();
    $this->ad = Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'street' => '12 Corniche St',
        'show_full_address' => false,
    ]);
    $this->attachImageRows($this->ad, 2);
});

/**
 * @return list<string>
 */
function queriesWhileShowing(string $adId): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    getJson("/api/v1/ads/{$adId}")->assertOk();
    DB::disableQueryLog();

    return array_column(DB::getQueryLog(), 'query');
}

it('renders the detail once and serves later visitors from the cache', function (): void {
    $firstVisit = queriesWhileShowing($this->ad->id);
    $secondVisit = queriesWhileShowing($this->ad->id);

    $mediaReads = array_filter($secondVisit, fn (string $sql): bool => str_contains($sql, 'from "media"'));

    expect(count($secondVisit))->toBeLessThan(count($firstVisit))
        ->and($mediaReads)->toBe([]);
});

it('shows an edit and a removed image right away', function (): void {
    getJson("/api/v1/ads/{$this->ad->id}")->assertJsonCount(2, 'data.images');

    $this->ad->update(['title' => 'A brand new title for this ad']);
    $this->ad->getMedia('images')->first()?->delete();

    getJson("/api/v1/ads/{$this->ad->id}")
        ->assertJsonPath('data.title', 'A brand new title for this ad')
        ->assertJsonCount(1, 'data.images');
});

it('stops serving the cached detail once the ad leaves the market', function (): void {
    getJson("/api/v1/ads/{$this->ad->id}")->assertOk();

    $this->ad->forceFill(['status' => AdStatus::EXPIRED])->save();

    getJson("/api/v1/ads/{$this->ad->id}")->assertNotFound();
});

it('keeps the favourite flag per viewer on top of the shared payload', function (): void {
    $fan = User::factory()->create();
    Favorite::query()->create(['user_id' => $fan->id, 'ad_id' => $this->ad->id]);

    getJson("/api/v1/ads/{$this->ad->id}")->assertJsonPath('data.is_favorited', false);

    Sanctum::actingAs($fan, ['*']);
    getJson("/api/v1/ads/{$this->ad->id}")->assertJsonPath('data.is_favorited', true);

    Sanctum::actingAs(User::factory()->create(), ['*']);
    getJson("/api/v1/ads/{$this->ad->id}")->assertJsonPath('data.is_favorited', false);
});

it('never hands the seller the visitors copy or visitors the private street', function (): void {
    getJson("/api/v1/ads/{$this->ad->id}")->assertJsonPath('data.street', null);

    Sanctum::actingAs($this->seller, ['*']);
    getJson("/api/v1/ads/{$this->ad->id}")->assertJsonPath('data.street', '12 Corniche St');

    Sanctum::actingAs(User::factory()->create(), ['*']);
    getJson("/api/v1/ads/{$this->ad->id}")->assertJsonPath('data.street', null);
});
