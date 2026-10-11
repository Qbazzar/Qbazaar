<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Jobs\Catalog\WarmCatalogCacheJob;
use App\Models\Ad;
use App\Models\User;
use App\Services\Ads\AdLifecycleService;
use App\Services\Catalog\HomeFeedCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
    $this->makeRailAd = fn (array $attributes = []): Ad => Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'views_count' => 0,
        'published_at' => now()->subHour(),
        ...$attributes,
    ]);
    $this->recommendedIds = fn (): array => collect(getJson('/api/v1/home')->assertOk()->json('data.recommended'))
        ->pluck('id')->all();
});

it('recommends only ads published inside the configured window when the rail is full', function (): void {
    config(['qbazaar.home.recommended_limit' => 2, 'qbazaar.home.recommended_window_hours' => 24]);
    $inside = ($this->makeRailAd)(['published_at' => now()->subHours(23)]);
    $recent = ($this->makeRailAd)(['published_at' => now()->subHours(2)]);
    ($this->makeRailAd)(['published_at' => now()->subHours(25), 'views_count' => 999]);

    expect(($this->recommendedIds)())->toBe([$recent->id, $inside->id]);
});

it('follows the window length from config', function (): void {
    config(['qbazaar.home.recommended_limit' => 2, 'qbazaar.home.recommended_window_hours' => 6]);
    $fresh = ($this->makeRailAd)(['published_at' => now()->subHours(5)]);
    $older = ($this->makeRailAd)(['published_at' => now()->subHours(10), 'views_count' => 1]);
    $oldest = ($this->makeRailAd)(['published_at' => now()->subHours(20), 'views_count' => 50]);

    // 6h window holds one ad; the top-up (most viewed) fills the second slot.
    expect(($this->recommendedIds)())->toBe([$fresh->id, $oldest->id]);
});

it('puts promoted ads first and then the newest ads first', function (): void {
    config(['qbazaar.home.recommended_limit' => 5]);
    $oldest = ($this->makeRailAd)(['published_at' => now()->subHours(20), 'views_count' => 900]);
    $newest = ($this->makeRailAd)(['published_at' => now()->subMinutes(10)]);
    $middle = ($this->makeRailAd)(['published_at' => now()->subHours(5), 'views_count' => 400]);
    $promoted = ($this->makeRailAd)(['published_at' => now()->subDays(40), 'promotion_rank' => 3]);

    $ids = ($this->recommendedIds)();

    expect($ids)->toBe([$promoted->id, $newest->id, $middle->id, $oldest->id]);
});

it('tops up with the most viewed ads of the last month only when fresh ads fall short', function (): void {
    config(['qbazaar.home.recommended_limit' => 4]);
    $fresh = ($this->makeRailAd)(['published_at' => now()->subHours(3)]);
    $quiet = ($this->makeRailAd)(['published_at' => now()->subDays(3), 'views_count' => 10]);
    $popular = ($this->makeRailAd)(['published_at' => now()->subDays(10), 'views_count' => 80]);
    ($this->makeRailAd)(['published_at' => now()->subDays(40), 'views_count' => 5000]);
    Ad::factory()->create(['user_id' => $this->seller->id, 'status' => AdStatus::DRAFT->value, 'views_count' => 9999]);

    expect(($this->recommendedIds)())->toBe([$fresh->id, $popular->id, $quiet->id]);
});

it('never repeats an ad between the promoted, fresh and top-up groups', function (): void {
    config(['qbazaar.home.recommended_limit' => 6]);
    ($this->makeRailAd)(['promotion_rank' => 2]);
    ($this->makeRailAd)();

    $ids = ($this->recommendedIds)();

    expect($ids)->toHaveCount(2)->and(array_unique($ids))->toHaveCount(2);
});

it('asks for a debounced home rebuild when an ad is published, approved, expires or is removed', function (string $event): void {
    config(['qbazaar.home.refresh_debounce_seconds' => 15]);
    $lifecycle = app(AdLifecycleService::class);
    $draft = Ad::factory()->create(['user_id' => $this->seller->id, 'status' => AdStatus::DRAFT->value]);
    $live = ($this->makeRailAd)();
    $submitted = $lifecycle->submitForReview($draft);
    Queue::fake();

    match ($event) {
        'approved' => $lifecycle->approve($submitted),
        'published' => ($this->makeRailAd)(),
        'expired' => $lifecycle->expire($live->fresh()),
        'removed' => $live->delete(),
    };

    Queue::assertPushed(WarmCatalogCacheJob::class, 1);
})->with(['approved', 'published', 'expired', 'removed']);

it('ignores an ad moving between draft and pending review', function (): void {
    config(['qbazaar.home.refresh_debounce_seconds' => 15]);
    $draft = Ad::factory()->create(['user_id' => $this->seller->id, 'status' => AdStatus::DRAFT->value]);
    Queue::fake();

    app(AdLifecycleService::class)->submitForReview($draft);

    Queue::assertNotPushed(WarmCatalogCacheJob::class);
});

it('coalesces a burst of listing events into one queued rebuild', function (): void {
    config(['qbazaar.home.refresh_debounce_seconds' => 15]);
    Queue::fake();

    ($this->makeRailAd)();
    ($this->makeRailAd)();
    ($this->makeRailAd)()->delete();

    Queue::assertPushed(WarmCatalogCacheJob::class, 1);
    Queue::assertPushed(WarmCatalogCacheJob::class, fn (WarmCatalogCacheJob $job): bool => $job->delay !== null);
});

it('does not queue a rebuild when the debounce is switched off', function (): void {
    config(['qbazaar.home.refresh_debounce_seconds' => 0]);
    Queue::fake();

    ($this->makeRailAd)();

    Queue::assertNotPushed(WarmCatalogCacheJob::class);
});

it('shows a published ad and drops a removed one once the rebuild runs', function (): void {
    $stays = ($this->makeRailAd)(['published_at' => now()->subHours(3)]);
    $removed = ($this->makeRailAd)(['published_at' => now()->subHours(2)]);
    expect(($this->recommendedIds)())->toBe([$removed->id, $stays->id]);

    $newest = ($this->makeRailAd)(['published_at' => now()]);
    $removed->delete();
    expect(($this->recommendedIds)())->toBe([$removed->id, $stays->id]);

    app(HomeFeedCache::class)->refresh();

    expect(($this->recommendedIds)())->toBe([$newest->id, $stays->id]);
});

it('keeps the home cache lifetime short enough for a 24 hour window', function (): void {
    expect((int) config('qbazaar.home.cache_seconds'))->toBeLessThanOrEqual(300)
        ->and((int) config('qbazaar.home.recommended_window_hours'))->toBe(24);
});
