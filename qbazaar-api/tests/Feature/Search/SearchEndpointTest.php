<?php

declare(strict_types=1);

use App\Enums\AdStatus;
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

it('returns active ads ranked by published_at desc when no query is provided', function (): void {
    $older = Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'title' => 'Older active listing',
        'published_at' => now()->subDays(5),
    ]);

    $newer = Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'title' => 'Brand new listing',
        'published_at' => now()->subHour(),
    ]);

    // Drafts must not show up in search results.
    Ad::factory()->create([
        'user_id' => $this->seller->id,
        'status' => AdStatus::DRAFT->value,
    ]);

    $this->waitForMeilisearch();

    $response = getJson('/api/v1/search', ['Accept' => 'application/json'])
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$newer->id, $older->id]);
});

it('matches ads by keyword in title or description', function (): void {
    $match = Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'title' => 'Mountain bike for sale',
        'description' => 'Lightly used aluminium frame mountain bike',
    ]);

    Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'title' => 'Family sedan',
        'description' => 'Reliable daily driver',
    ]);

    $this->waitForMeilisearch();

    $response = getJson('/api/v1/search?q=mountain', ['Accept' => 'application/json'])
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($match->id)
        ->and(count($ids))->toBe(1);
});

it('narrows results with a price range filter', function (): void {
    $cheap = Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'title' => 'Bargain item',
        'price' => 50,
    ]);

    $midRange = Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'title' => 'Mid range thing',
        'price' => 300,
    ]);

    Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'title' => 'Expensive widget',
        'price' => 5000,
    ]);

    $this->waitForMeilisearch();

    $response = getJson('/api/v1/search?price_min=100&price_max=1000', ['Accept' => 'application/json'])
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($midRange->id)
        ->and($ids)->not->toContain($cheap->id);
});

it('returns a category_slug filter via slug resolution', function (): void {
    /** @var Category $categoryA */
    $categoryA = Category::query()->first();
    /** @var Category $categoryB */
    $categoryB = Category::query()->skip(1)->first();

    $adA = Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'category_id' => $categoryA->id,
        'title' => 'In category A',
    ]);

    Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'category_id' => $categoryB->id,
        'title' => 'In category B',
    ]);

    $this->waitForMeilisearch();

    $response = getJson("/api/v1/search?category_slug={$categoryA->slug}", ['Accept' => 'application/json'])
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$adA->id]);
});

it('reports pagination metadata in the envelope', function (): void {
    Ad::factory()->count(3)->active()->create(['user_id' => $this->seller->id]);

    $this->waitForMeilisearch();

    $response = getJson('/api/v1/search?per_page=2', ['Accept' => 'application/json'])
        ->assertOk();

    $meta = $response->json('meta');

    expect($meta)->toMatchArray([
        'current_page' => 1,
        'per_page' => 2,
    ])->and($meta['total'])->toBe(3)
        ->and($meta['last_page'])->toBe(2);
});

it('rejects invalid sort values via SearchRequest validation', function (): void {
    getJson('/api/v1/search?sort=banana', ['Accept' => 'application/json'])
        ->assertStatus(422);
});
