<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\Ad;
use App\Models\User;
use App\Services\Catalog\CategoryAdCounts;
use App\Services\Catalog\LocationAdCounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
});

/**
 * @return list<string>
 */
function sqlDuring(Closure $request): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    DB::disableQueryLog();

    return array_map(fn (array $query): string => $query['query'], DB::getQueryLog());
}

it('copies the seller status onto a new ad', function (): void {
    $suspended = User::factory()->create(['status' => UserStatus::SUSPENDED->value]);

    expect($this->makeAd($this->seller, ['status' => 'active'])->seller_active)->toBeTrue()
        ->and($this->makeAd($suspended, ['status' => 'active'])->seller_active)->toBeFalse();
});

it('keeps seller_active in step with the seller status on every ad of theirs', function (): void {
    $live = $this->makeAd($this->seller, ['status' => 'active', 'published_at' => now()]);
    $draft = $this->makeAd($this->seller, ['status' => 'draft']);

    $this->seller->forceFill(['status' => UserStatus::SUSPENDED])->save();

    expect($live->fresh()->seller_active)->toBeFalse()
        ->and($draft->fresh()->seller_active)->toBeFalse();

    $this->seller->forceFill(['status' => UserStatus::ACTIVE])->save();

    expect($live->fresh()->seller_active)->toBeTrue()
        ->and($draft->fresh()->seller_active)->toBeTrue();
});

it('filters the public feed on the stored flag instead of joining users', function (): void {
    $this->makeAd($this->seller, ['status' => 'active', 'published_at' => now()]);

    $queries = sqlDuring(fn () => getJson('/api/v1/ads')->assertOk()->assertJsonCount(1, 'data'));

    $adQueries = array_filter($queries, fn (string $sql): bool => Str::contains($sql, 'from "ads"'));

    expect($adQueries)->not->toBeEmpty();

    foreach ($adQueries as $sql) {
        expect($sql)->not->toContain('"users"');
    }
});

/**
 * @param list<string> $queries
 * @return list<string>
 */
function countQueries(array $queries): array
{
    return array_values(array_filter($queries, fn (string $sql): bool => Str::contains($sql, 'count(', ignoreCase: true)));
}

it('takes the feed total from the warmed catalog counters', function (string $filter): void {
    $ad = $this->makeAd($this->seller, ['status' => 'active', 'published_at' => now()]);
    $query = match ($filter) {
        'none' => '',
        'category' => 'category_id=' . $ad->category_id,
        'location' => 'location_id=' . $ad->location_id,
    };
    app(CategoryAdCounts::class)->refresh();
    app(LocationAdCounts::class)->refresh();

    $queries = sqlDuring(fn () => getJson("/api/v1/ads?{$query}")->assertOk()->assertJsonPath('meta.total', 1));

    expect(countQueries($queries))->toBe([]);
})->with(['none', 'category', 'location']);

it('counts other filter sets once and reuses the total for every page and sort', function (): void {
    $this->makeAd($this->seller, ['status' => 'active', 'published_at' => now(), 'price' => 100]);

    getJson('/api/v1/ads?price_min=50&sort=latest')->assertOk()->assertJsonPath('meta.total', 1);

    $queries = sqlDuring(fn () => getJson('/api/v1/ads?price_min=50&sort=price_asc&page=2')
        ->assertOk()
        ->assertJsonPath('meta.total', 1));

    expect(countQueries($queries))->toBe([]);
});

it('recounts a zero total so a first listing is never hidden behind an empty page', function (): void {
    getJson('/api/v1/ads?price_min=50')->assertOk()->assertJsonPath('meta.total', 0);

    $this->makeAd($this->seller, ['status' => 'active', 'published_at' => now(), 'price' => 100]);

    getJson('/api/v1/ads?price_min=50')->assertOk()->assertJsonCount(1, 'data');
});

it('takes a seller page total from the stored active-ads counter', function (): void {
    $this->makeAd($this->seller, ['status' => 'active', 'published_at' => now()]);
    $this->makeAd($this->seller, ['status' => 'active', 'published_at' => now()]);
    $this->makeAd($this->seller, ['status' => 'draft']);

    $queries = sqlDuring(fn () => getJson("/api/v1/users/{$this->seller->id}/ads")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2));

    expect(countQueries($queries))->toBe([]);
});

it('does not load the seller to decide whether an ad is publicly listed', function (): void {
    $ad = $this->makeAd($this->seller, ['status' => 'active', 'published_at' => now()]);
    $fresh = Ad::query()->findOrFail($ad->id);

    expect($fresh->isPubliclyListed())->toBeTrue()
        ->and($fresh->relationLoaded('user'))->toBeFalse();
});

it('stops offset paging at the configured depth', function (): void {
    config(['qbazaar.ads.feed_max_page' => 3]);

    getJson('/api/v1/ads?page=3')->assertOk();
    getJson('/api/v1/ads?page=4')->assertStatus(422);
});
