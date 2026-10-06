<?php

declare(strict_types=1);

use App\Actions\Catalog\GetHomeFeedAction;
use App\Models\Ad;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    Cache::flush();
    [$this->older, $this->newer] = Location::query()->whereNotNull('parent_id')->orderBy('id')->limit(2)->get()->all();
    $this->shop = User::factory()->business()->create();
});

function listShopAd(User $shop, Location $location, string $publishedAt, array $overrides = []): Ad
{
    return Ad::factory()->active()->create([
        'user_id' => $shop->id,
        'location_id' => $location->id,
        'published_at' => $publishedAt,
        ...$overrides,
    ]);
}

/**
 * @return array<string, mixed>
 */
function locationSummary(Location $location): array
{
    return [
        'id' => $location->id,
        'parent_id' => $location->parent_id,
        'slug' => $location->slug,
        'name' => $location->name,
        'type' => $location->type->value,
    ];
}

it('shows where a featured seller sells from, with followers and join date', function (): void {
    listShopAd($this->shop, $this->older, now()->subDays(3)->toDateTimeString());
    listShopAd($this->shop, $this->newer, now()->subDay()->toDateTimeString());
    $this->shop->forceFill(['followers_count' => 12])->save();

    $seller = getJson('/api/v1/home')->assertOk()->json('data.featured_sellers.0');

    expect($seller['id'])->toBe($this->shop->id)
        ->and($seller['followers_count'])->toBe(12)
        ->and($seller['joined_at'])->toBe($this->shop->created_at->toIso8601String())
        ->and($seller['location'])->toBe(locationSummary($this->newer));
});

it('ignores unlisted ads when working out the seller location', function (): void {
    listShopAd($this->shop, $this->older, now()->subDays(3)->toDateTimeString());
    Ad::factory()->draft()->create(['user_id' => $this->shop->id, 'location_id' => $this->newer->id]);

    $seller = getJson('/api/v1/home')->json('data.featured_sellers.0');

    expect($seller['location']['id'])->toBe($this->older->id);
});

it('keeps the seller location at city or area level, without pin, street or contact details', function (): void {
    $ad = listShopAd($this->shop, $this->newer, now()->subDay()->toDateTimeString(), [
        'latitude' => 25.3184,
        'longitude' => 51.5310,
        'street' => 'Street 820, Building 14',
        'show_full_address' => true,
    ]);

    $seller = getJson('/api/v1/home')->json('data.featured_sellers.0');
    $user = getJson("/api/v1/ads/{$ad->id}")->assertOk()->json('data.user');

    expect($seller)->not->toHaveKeys(['phone', 'email', 'street', 'address'])
        ->and($seller['location'])->toBe(locationSummary($this->newer))
        ->and($user['location'])->toBe(locationSummary($this->newer));
});

it('loads the seller locations of the home feed with a constant number of queries', function (): void {
    listShopAd($this->shop, $this->older, now()->toDateTimeString());
    $queries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(GetHomeFeedAction::class)->execute();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $queries(); // the first run also fills the shared counters
    $one = $queries();
    foreach (range(1, 4) as $unused) {
        listShopAd(User::factory()->business()->create(), $this->newer, now()->toDateTimeString());
    }

    expect($queries())->toBe($one);
});

it('adds the seller location to the user of the ad detail', function (): void {
    listShopAd($this->shop, $this->older, now()->subDays(3)->toDateTimeString());
    $newest = listShopAd($this->shop, $this->newer, now()->subDay()->toDateTimeString());

    $user = getJson("/api/v1/ads/{$newest->id}")->assertOk()->json('data.user');

    expect($user['location'])->toBe(locationSummary($this->newer))
        ->and($user)->toHaveKeys(['id', 'full_name', 'joined_at', 'followers_count']);
});

it('gives a null location when the seller has nothing listed', function (): void {
    $sold = Ad::factory()->sold()->create(['user_id' => $this->shop->id, 'location_id' => $this->older->id]);

    $user = getJson("/api/v1/ads/{$sold->id}")->assertOk()->json('data.user');

    expect($user)->toHaveKey('location')
        ->and($user['location'])->toBeNull();
});

it('leaves the location off user payloads that do not load it', function (): void {
    listShopAd($this->shop, $this->older, now()->toDateTimeString());

    getJson("/api/v1/users/{$this->shop->id}/public-profile")->assertOk()->assertJsonMissingPath('data.location');
});
