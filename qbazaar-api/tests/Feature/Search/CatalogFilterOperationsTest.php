<?php

declare(strict_types=1);

use App\Data\Catalog\AdFeedFilters;
use App\Jobs\Catalog\WarmCatalogCacheJob;
use App\Jobs\Search\SyncSellerAccountTypeJob;
use App\Models\Ad;
use App\Models\Category;
use App\Models\User;
use App\Services\Search\AdFacetCounter;
use App\Services\Search\AdListingFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\artisan;
use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
    $this->boats = Category::query()->where('slug', 'boats')->firstOrFail();
    $this->listed = ['status' => 'active', 'published_at' => now(), 'category_id' => $this->boats->id];
});

function facetAggregates(Closure $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    DB::disableQueryLog();

    return count(array_filter(DB::getQueryLog(), fn (array $q): bool => str_contains(strtolower($q['query']), 'buy_now__true')));
}

it('serves the unfiltered facets from the warmer, not from the request', function (): void {
    $this->makeAd($this->seller, [...$this->listed, 'ad_type' => 'wanted']);
    app()->call([new WarmCatalogCacheJob, 'handle']);

    $this->makeAd($this->seller, [...$this->listed, 'ad_type' => 'wanted']);

    $queries = facetAggregates(fn () => expect(getJson('/api/v1/ads')->json('meta.facets.ad_type.wanted'))->toBe(1));

    expect($queries)->toBe(0);
});

it('counts a narrowed set once and shares it, with price bounds rounded to the same step', function (): void {
    $this->makeAd($this->seller, [...$this->listed, 'price' => 70]);

    $first = facetAggregates(fn () => getJson("/api/v1/ads?category_id={$this->boats->id}&price_min=60")->assertOk());
    $second = facetAggregates(fn () => getJson("/api/v1/ads?category_id={$this->boats->id}&price_min=55")->assertOk());

    expect($first)->toBe(1)
        ->and($second)->toBe(0);
});

it('counts every request when the facet cache is off', function (): void {
    config(['qbazaar.search.facets_cache_seconds' => 0]);

    expect(facetAggregates(fn () => getJson('/api/v1/ads')->assertOk()))->toBe(1)
        ->and(facetAggregates(fn () => getJson('/api/v1/ads')->assertOk()))->toBe(1);
});

it('moves every cached count to a new version when flushed', function (): void {
    $this->makeAd($this->seller, [...$this->listed, 'ad_type' => 'wanted']);
    getJson('/api/v1/ads')->assertOk();
    $this->makeAd($this->seller, [...$this->listed, 'ad_type' => 'wanted']);

    app(AdFacetCounter::class)->flush();

    expect(getJson('/api/v1/ads')->json('meta.facets.ad_type.wanted'))->toBe(2);
});

it('answers empty counts and logs when a count runs past its time limit', function (): void {
    Log::spy();
    $timeout = new QueryException('mysql', 'select 1', [], tap(new PDOException('max_statement_time exceeded'), fn (PDOException $e) => $e->errorInfo = ['70100', 1969, 'timeout']));
    $filters = Mockery::mock(AdListingFilters::class);
    // Only the facet count fails; the page itself is read normally.
    $filters->shouldReceive('apply')->once()->andThrow($timeout);
    $filters->shouldReceive('apply')->andReturnArg(0);
    app()->instance(AdListingFilters::class, $filters);

    $response = getJson('/api/v1/ads?ad_type=wanted');

    $response->assertOk();
    expect($response->getContent())->toContain('"ad_type":{}');
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'Facet counts timed out'))->atLeast()->once();
});

it('lets other database errors through', function (): void {
    $broken = new QueryException('mysql', 'select 1', [], tap(new PDOException('gone away'), fn (PDOException $e) => $e->errorInfo = ['HY000', 2006, 'gone']));
    $filters = Mockery::mock(AdListingFilters::class);
    $filters->shouldReceive('apply')->andThrow($broken);
    app()->instance(AdListingFilters::class, $filters);

    getJson('/api/v1/ads?ad_type=wanted')->assertStatus(500);
});

it('gives the same filters in another order the same fingerprint', function (): void {
    $one = new AdFeedFilters(customFields: ['year' => ['max' => 2020, 'min' => 2015], 'make' => 'Toyota']);
    $other = new AdFeedFilters(customFields: ['make' => 'Toyota', 'year' => ['min' => 2015, 'max' => 2020]]);

    expect($one->fingerprint())->toBe($other->fingerprint());
});

it('copies a changed account type to the seller ads through a queued job', function (): void {
    Queue::fake();
    $ad = $this->makeAd($this->seller, $this->listed);

    $this->seller->forceFill(['account_type' => 'business'])->save();

    Queue::assertPushedOn('search', SyncSellerAccountTypeJob::class, fn (SyncSellerAccountTypeJob $job): bool => $job->userId === $this->seller->id && $job->afterCommit);
    expect($ad->fresh()->seller_type->value)->toBe('private');
});

it('syncs the seller ads and the facets when the job runs', function (): void {
    $ad = $this->makeAd($this->seller, $this->listed);
    $other = $this->makeAd(User::factory()->create(), $this->listed);
    getJson('/api/v1/ads?ad_type=offering')->assertOk();

    $this->seller->forceFill(['account_type' => 'business'])->save();

    expect($ad->fresh()->seller_type->value)->toBe('business')
        ->and($other->fresh()->seller_type->value)->toBe('private')
        ->and(getJson('/api/v1/ads?ad_type=offering')->json('meta.facets.seller_type'))->toEqual(['business' => 1, 'private' => 1]);
});

it('does not queue anything when the account type is unchanged', function (): void {
    Queue::fake();

    $this->seller->forceFill(['full_name' => 'Someone Else'])->save();

    Queue::assertNothingPushed();
});

it('backfills business sellers in batches and can run again', function (): void {
    $business = User::factory()->business()->create();
    $businessAds = [$this->makeAd($business, $this->listed), $this->makeAd($business, [...$this->listed, 'status' => 'draft'])];
    $privateAd = $this->makeAd($this->seller, $this->listed);
    DB::table('ads')->update(['seller_type' => 'private']);

    artisan('ads:sync-seller-types')->expectsOutput('Updated 2 ads.')->assertSuccessful();

    expect($businessAds[0]->fresh()->seller_type->value)->toBe('business')
        ->and($businessAds[1]->fresh()->seller_type->value)->toBe('business')
        ->and($privateAd->fresh()->seller_type->value)->toBe('private');

    artisan('ads:sync-seller-types')->expectsOutput('Updated 0 ads.')->assertSuccessful();
});

it('gives new ads the account type of their seller', function (): void {
    $business = User::factory()->business()->create();

    expect($this->makeAd($business, $this->listed)->seller_type->value)->toBe('business')
        ->and($this->makeAd($this->seller, $this->listed)->seller_type->value)->toBe('private')
        ->and(Ad::query()->count())->toBe(2);
});

function timingOutFilters(int $failures = 1): AdListingFilters
{
    $real = app(AdListingFilters::class);
    $remaining = $failures;
    $timeout = new QueryException('mysql', 'select 1', [], tap(new PDOException('max_statement_time exceeded'), fn (PDOException $e) => $e->errorInfo = ['70100', 1969, 'timeout']));

    $filters = Mockery::mock(AdListingFilters::class);
    $filters->shouldReceive('apply')->andReturnUsing(function (Builder $query, AdFeedFilters $feed) use ($real, &$remaining, $timeout) {
        // The page query shares this class; only the facet count (which has no sort and no limit) may time out.
        if ($remaining > 0 && $query->getQuery()->orders === null && $query->getQuery()->limit === null && $query->getEagerLoads() === []) {
            $remaining--;

            throw $timeout;
        }

        return $real->apply($query, $feed);
    });

    return $filters;
}

it('does not cache a timed-out count, so the next request counts again', function (): void {
    $this->makeAd($this->seller, [...$this->listed, 'ad_type' => 'wanted']);
    app()->instance(AdListingFilters::class, timingOutFilters());

    $first = getJson('/api/v1/ads?ad_type=wanted')->assertOk();
    $second = getJson('/api/v1/ads?ad_type=wanted')->assertOk();

    expect($first->getContent())->toContain('"ad_type":{}')
        ->and($second->json('meta.facets.ad_type'))->toBe(['wanted' => 1]);
});

it('keeps the previous unfiltered counts when the warmer times out', function (): void {
    $this->makeAd($this->seller, [...$this->listed, 'ad_type' => 'wanted']);
    expect(getJson('/api/v1/ads')->json('meta.facets.ad_type'))->toBe(['wanted' => 1]);

    app()->instance(AdListingFilters::class, timingOutFilters());
    app(AdFacetCounter::class)->refreshUnfiltered();

    expect(getJson('/api/v1/ads')->json('meta.facets.ad_type'))->toBe(['wanted' => 1]);
});
