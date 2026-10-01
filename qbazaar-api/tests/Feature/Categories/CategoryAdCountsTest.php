<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Jobs\Catalog\WarmCatalogCacheJob;
use App\Models\Ad;
use App\Models\Category;
use App\Models\User;
use App\Services\Ads\AdLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();

    $this->vehicles = Category::query()->where('slug', 'vehicles')->firstOrFail();
    $this->cars = Category::query()->where('slug', 'cars')->firstOrFail();
    $this->sedans = Category::query()->create([
        'parent_id' => $this->cars->id,
        'slug' => 'sedans',
        'name' => ['ar' => 'سيدان', 'en' => 'Sedans'],
        'order' => 0,
    ]);
});

function listedAdIn(Category $category, User $seller, array $overrides = []): Ad
{
    return Ad::factory()->active()->create(array_merge([
        'user_id' => $seller->id,
        'category_id' => $category->id,
        'published_at' => now()->subDays(3),
    ], $overrides));
}

function treeNode(string $slug): ?array
{
    $flatten = function (array $nodes) use (&$flatten): array {
        return collect($nodes)->flatMap(fn (array $node): array => [$node, ...$flatten($node['children'])])->all();
    };

    return collect($flatten(getJson('/api/v1/categories/tree')->assertOk()->json('data')))
        ->firstWhere('slug', $slug);
}

it('counts active ads of the category and all its descendants', function (): void {
    listedAdIn($this->sedans, $this->seller);
    listedAdIn($this->cars, $this->seller);
    listedAdIn($this->vehicles, $this->seller, ['published_at' => now()]);

    expect(treeNode('vehicles'))->toMatchArray(['ads_count' => 3, 'today_count' => 1])
        ->and(treeNode('cars'))->toMatchArray(['ads_count' => 2, 'today_count' => 0])
        ->and(treeNode('sedans'))->toMatchArray(['ads_count' => 1, 'today_count' => 0]);
});

it('ignores ads that are not publicly listed', function (): void {
    listedAdIn($this->sedans, $this->seller, ['status' => AdStatus::DRAFT->value]);
    listedAdIn($this->sedans, $this->seller, ['status' => AdStatus::SOLD->value]);
    listedAdIn($this->sedans, User::factory()->suspended()->create());
    listedAdIn($this->sedans, $this->seller)->delete();

    expect(treeNode('vehicles')['ads_count'])->toBe(0);
});

it('picks up published and expired ads on the next warm-up, not per event', function (): void {
    $draft = listedAdIn($this->sedans, $this->seller, ['status' => AdStatus::DRAFT->value]);
    expect(treeNode('vehicles')['ads_count'])->toBe(0);

    $lifecycle = app(AdLifecycleService::class);
    $live = $lifecycle->approve($lifecycle->submitForReview($draft));
    expect(treeNode('vehicles')['ads_count'])->toBe(0);

    WarmCatalogCacheJob::dispatchSync();
    expect(treeNode('vehicles'))->toMatchArray(['ads_count' => 1, 'today_count' => 1]);

    $lifecycle->expire($live);
    WarmCatalogCacheJob::dispatchSync();
    expect(treeNode('vehicles')['ads_count'])->toBe(0);
});

it('serves the counts from the cache between changes', function (): void {
    listedAdIn($this->sedans, $this->seller);
    treeNode('vehicles');

    DB::enableQueryLog();
    getJson('/api/v1/categories/tree')->assertOk();

    $adQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], '"ads"'));
    expect($adQueries)->toBeEmpty();
});

it('builds the tree without a query per category', function (): void {
    DB::enableQueryLog();
    getJson('/api/v1/categories/tree')->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(3);
});

it('hides the subtree of an inactive category', function (): void {
    $this->cars->update(['is_active' => false]);

    expect(treeNode('cars'))->toBeNull()
        ->and(treeNode('sedans'))->toBeNull()
        ->and(treeNode('vehicles'))->not->toBeNull();
});

it('reports real stats for a category', function (): void {
    listedAdIn($this->sedans, $this->seller);
    listedAdIn($this->cars, $this->seller, ['published_at' => now()]);

    getJson('/api/v1/categories/cars/stats')
        ->assertOk()
        ->assertJsonPath('data', ['ads_count' => 2, 'sub_ads_count' => 1, 'today_count' => 1]);
});

it('returns CAT_001 for the stats of an unknown category', function (): void {
    getJson('/api/v1/categories/no-such-category/stats')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'CAT_001');
});
