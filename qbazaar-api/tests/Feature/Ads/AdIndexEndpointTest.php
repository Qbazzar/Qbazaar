<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
});

it('only returns active ads on the public feed', function (): void {
    $active = $this->makeAd($this->seller, [
        'status' => AdStatus::ACTIVE->value,
        'published_at' => now(),
    ]);
    $this->makeAd($this->seller, ['status' => AdStatus::DRAFT->value]);
    $this->makeAd($this->seller, ['status' => AdStatus::SOLD->value]);

    $response = getJson('/api/v1/ads', ['Accept' => 'application/json'])
        ->assertOk();

    $data = $response->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['id'])->toBe($active->id);
});

it('filters by category_id when supplied', function (): void {
    // Pick two seeded categories — we don't need a factory because the
    // catalogue seeder ships >= 2 rows.
    $categories = Category::query()->limit(2)->get();

    expect($categories)->toHaveCount(2);

    /** @var Category $categoryA */
    $categoryA = $categories[0];
    /** @var Category $categoryB */
    $categoryB = $categories[1];

    $adA = Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'category_id' => $categoryA->id,
    ]);
    Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'category_id' => $categoryB->id,
    ]);

    $response = getJson(
        "/api/v1/ads?category_id={$categoryA->id}",
        ['Accept' => 'application/json'],
    )->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$adA->id]);
});
