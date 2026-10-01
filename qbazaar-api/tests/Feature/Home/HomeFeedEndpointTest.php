<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Jobs\Catalog\WarmCatalogCacheJob;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Services\Ads\AdLifecycleService;
use App\Services\Catalog\CatalogCache;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
});

it('returns every home section in one response', function (): void {
    Ad::factory()->active()->create(['user_id' => $this->seller->id]);

    $response = getJson('/api/v1/home')->assertOk();

    expect(array_keys($response->json('data')))
        ->toBe(['categories', 'recommended', 'featured_sellers', 'best_selling', 'places'])
        ->and(collect($response->json('data.categories'))->pluck('slug')->all())
        ->toBe(Category::query()->whereNull('parent_id')->where('is_active', true)->orderBy('order')->pluck('slug')->all());
});

it('recommends the most viewed recent ads and skips old or unlisted ones', function (): void {
    $popular = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'views_count' => 90]);
    $quiet = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'views_count' => 5]);
    Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'views_count' => 500,
        'published_at' => now()->subDays((int) config('qbazaar.home.recommended_window_days') + 1),
    ]);
    Ad::factory()->create(['user_id' => $this->seller->id, 'status' => AdStatus::DRAFT->value, 'views_count' => 999]);

    $ids = collect(getJson('/api/v1/home')->json('data.recommended'))->pluck('id')->all();

    expect($ids)->toBe([$popular->id, $quiet->id]);
});

it('ranks best selling by favourites', function (): void {
    $loved = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'favorites_count' => 40]);
    $liked = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'favorites_count' => 3]);

    $ids = collect(getJson('/api/v1/home')->json('data.best_selling'))->pluck('id')->all();

    expect($ids)->toBe([$loved->id, $liked->id]);
});

it('features active business sellers that have live ads', function (): void {
    $busyShop = User::factory()->business()->create();
    Ad::factory()->count(2)->active()->create(['user_id' => $busyShop->id]);
    $smallShop = User::factory()->business()->create();
    Ad::factory()->active()->create(['user_id' => $smallShop->id]);
    $emptyShop = User::factory()->business()->create();
    Ad::factory()->create(['user_id' => $emptyShop->id, 'status' => AdStatus::DRAFT->value]);
    Ad::factory()->active()->create(['user_id' => User::factory()->business()->suspended()->create()->id]);

    $sellers = getJson('/api/v1/home')->json('data.featured_sellers');

    expect(collect($sellers)->pluck('id')->all())->toBe([$busyShop->id, $smallShop->id])
        ->and($sellers[0]['ads_count'])->toBe(2)
        ->and($sellers[0])->not->toHaveKeys(['phone', 'email']);
});

it('lists the cities with their live ad counts', function (): void {
    $district = Location::query()->whereNotNull('parent_id')->firstOrFail();
    Ad::factory()->count(2)->active()->create(['user_id' => $this->seller->id, 'location_id' => $district->id]);

    $places = collect(getJson('/api/v1/home')->json('data.places'));

    expect($places->pluck('id')->all())
        ->toBe(Location::query()->whereNull('parent_id')->orderBy('order')->pluck('id')->all())
        ->and($places->firstWhere('id', $district->parent_id)['ads_count'])->toBe(2);
});

it('keeps serving the cached feed while ads change, and the warmer refreshes it', function (): void {
    getJson('/api/v1/home')->assertOk();
    expect(Cache::has(CatalogCache::HOME_FEED_KEY))->toBeTrue();

    $ad = Ad::factory()->create(['user_id' => $this->seller->id, 'status' => AdStatus::DRAFT->value]);
    $lifecycle = app(AdLifecycleService::class);
    $lifecycle->approve($lifecycle->submitForReview($ad));

    DB::flushQueryLog();
    DB::enableQueryLog();
    $bestSelling = getJson('/api/v1/home')->assertOk()->json('data.best_selling');
    DB::disableQueryLog();

    expect(DB::getQueryLog())->toBeEmpty()
        ->and(collect($bestSelling)->pluck('id'))->not->toContain($ad->id);

    WarmCatalogCacheJob::dispatchSync();

    expect(collect(getJson('/api/v1/home')->json('data.best_selling'))->pluck('id'))->toContain($ad->id);
});

it('warms the catalog on the scheduler', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => $event->description === 'catalog.warm-cache');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/2 * * * *');
});
