<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;
use Tests\Concerns\InteractsWithMeilisearch;

uses(RefreshDatabase::class, CreatesAds::class, InteractsWithMeilisearch::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
    $this->flushAdsIndex();
});

it('returns category and location facet counts that add up to total hits', function (): void {
    /** @var Category $categoryA */
    $categoryA = Category::query()->first();
    /** @var Category $categoryB */
    $categoryB = Category::query()->skip(1)->first();

    Ad::factory()->count(3)->active()->create([
        'user_id' => $this->seller->id,
        'category_id' => $categoryA->id,
    ]);

    Ad::factory()->count(2)->active()->create([
        'user_id' => $this->seller->id,
        'category_id' => $categoryB->id,
    ]);

    $this->waitForMeilisearch();

    $response = getJson('/api/v1/search', ['Accept' => 'application/json'])
        ->assertOk();

    $facets = $response->json('facets');

    expect($facets)->toHaveKey('categories')
        ->and($facets['categories'][$categoryA->slug] ?? 0)->toBe(3)
        ->and($facets['categories'][$categoryB->slug] ?? 0)->toBe(2);

    $total = $response->json('meta.total');
    expect($total)->toBe(5);
});

it('exposes static price buckets in the facet envelope', function (): void {
    Ad::factory()->active()->create(['user_id' => $this->seller->id]);

    $this->waitForMeilisearch();

    $response = getJson('/api/v1/search', ['Accept' => 'application/json'])
        ->assertOk();

    $buckets = $response->json('facets.price_buckets');

    expect($buckets)->toBeArray()
        ->and(count($buckets))->toBeGreaterThan(0)
        ->and($buckets[0])->toHaveKeys(['label', 'min', 'max']);
});
