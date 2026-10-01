<?php

declare(strict_types=1);

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
    $this->viewer = User::factory()->create();
    $this->liked = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'featured' => true]);
    $this->other = Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'featured' => true,
        'category_id' => $this->liked->category_id,
    ]);
    Favorite::query()->create(['user_id' => $this->viewer->id, 'ad_id' => $this->liked->id]);
});

/**
 * @param list<array<string, mixed>> $cards
 * @return array<string, bool>
 */
function favoriteFlags(array $cards): array
{
    return collect($cards)->mapWithKeys(fn (array $card): array => [$card['id'] => $card['is_favorited']])->all();
}

it('reports false for guests on lists and detail', function (): void {
    expect(favoriteFlags(getJson('/api/v1/ads')->assertOk()->json('data')))
        ->toEqualCanonicalizing([$this->liked->id => false, $this->other->id => false])
        ->and(getJson("/api/v1/ads/{$this->liked->id}")->assertOk()->json('data.is_favorited'))->toBeFalse();
});

it('flags the viewer favourites on the public feed and the detail page', function (): void {
    Sanctum::actingAs($this->viewer, ['*']);

    $flags = favoriteFlags(getJson('/api/v1/ads')->assertOk()->json('data'));

    expect($flags[$this->liked->id])->toBeTrue()
        ->and($flags[$this->other->id])->toBeFalse()
        ->and(getJson("/api/v1/ads/{$this->liked->id}")->json('data.is_favorited'))->toBeTrue()
        ->and(getJson("/api/v1/ads/{$this->other->id}")->json('data.is_favorited'))->toBeFalse();
});

it('flags favourites on featured, similar, seller and id-lookup lists', function (): void {
    Sanctum::actingAs($this->viewer, ['*']);

    $featured = favoriteFlags(getJson('/api/v1/ads/featured')->assertOk()->json('data'));
    $similar = favoriteFlags(getJson("/api/v1/ads/{$this->other->id}/similar")->assertOk()->json('data'));
    $sellerAds = favoriteFlags(getJson("/api/v1/users/{$this->seller->id}/ads")->assertOk()->json('data'));
    $lookup = favoriteFlags(getJson("/api/v1/ads?ids={$this->other->id},{$this->liked->id}")->assertOk()->json('data'));

    expect($featured[$this->liked->id])->toBeTrue()
        ->and($featured[$this->other->id])->toBeFalse()
        ->and($similar[$this->liked->id])->toBeTrue()
        ->and($sellerAds[$this->liked->id])->toBeTrue()
        ->and($lookup)->toBe([$this->other->id => false, $this->liked->id => true]);
});

it('lays the viewer favourites over the shared cached home feed', function (): void {
    $guestCards = getJson('/api/v1/home')->assertOk()->json('data.best_selling');
    expect(collect($guestCards)->pluck('is_favorited')->unique()->all())->toBe([false]);

    Sanctum::actingAs($this->viewer, ['*']);
    $flags = favoriteFlags(getJson('/api/v1/home')->assertOk()->json('data.best_selling'));

    expect($flags[$this->liked->id])->toBeTrue()
        ->and($flags[$this->other->id])->toBeFalse();
});

it('resolves the flags with one query per page however many ads it holds', function (): void {
    Sanctum::actingAs($this->viewer, ['*']);

    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        getJson('/api/v1/ads')->assertOk();
        DB::disableQueryLog();

        return count(array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], '"favorites"')));
    };

    $few = $count();
    Ad::factory()->count(5)->active()->create(['user_id' => $this->seller->id]);

    expect($count())->toBe($few)->toBe(1);
});
