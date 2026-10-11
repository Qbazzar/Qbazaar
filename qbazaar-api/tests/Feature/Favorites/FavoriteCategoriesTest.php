<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\FavoriteCategory;
use App\Models\User;
use App\Services\Catalog\CatalogCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->user = User::factory()->create();
    $this->cars = Category::query()->where('slug', 'cars')->firstOrFail();
    Sanctum::actingAs($this->user, ['*']);
});

it('adds a category once however often PUT is repeated', function (): void {
    putJson('/api/v1/account/favorite-categories/cars')->assertOk()->assertJsonPath('data.favorited', true);
    putJson('/api/v1/account/favorite-categories/cars')->assertOk()->assertJsonPath('data.favorited', true);

    expect(FavoriteCategory::query()->where('user_id', $this->user->id)->count())->toBe(1);
});

it('removes a category idempotently', function (): void {
    putJson('/api/v1/account/favorite-categories/cars')->assertOk();

    deleteJson('/api/v1/account/favorite-categories/cars')->assertOk()->assertJsonPath('data.favorited', false);
    deleteJson('/api/v1/account/favorite-categories/cars')->assertOk()->assertJsonPath('data.favorited', false);

    expect(FavoriteCategory::query()->count())->toBe(0);
});

it('lists the favourites newest first without a query per row', function (): void {
    $other = Category::query()->where('slug', 'motorcycles')->firstOrFail();
    FavoriteCategory::factory()->create(['user_id' => $this->user->id, 'category_id' => $this->cars->id, 'created_at' => now()->subDay()]);
    FavoriteCategory::factory()->create(['user_id' => $this->user->id, 'category_id' => $other->id, 'created_at' => now()]);
    FavoriteCategory::factory()->create(['category_id' => $this->cars->id]);

    DB::enableQueryLog();
    $response = getJson('/api/v1/account/favorite-categories')->assertOk();
    $categoryQueries = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'from "categories"'));

    expect($response->json('data.*.slug'))->toBe(['motorcycles', 'cars'])
        ->and($response->json('data.0'))->toHaveKeys(['id', 'slug', 'name', 'icon', 'favorited_at'])
        ->and($categoryQueries->count())->toBeLessThanOrEqual(2);
});

it('answers 404 CAT_001 for an unknown category', function (): void {
    putJson('/api/v1/account/favorite-categories/nope')->assertNotFound()->assertJsonPath('error.code', 'CAT_001');
    deleteJson('/api/v1/account/favorite-categories/nope')->assertNotFound()->assertJsonPath('error.code', 'CAT_001');
});

it('refuses a hidden category on add but lets it leave the list', function (): void {
    FavoriteCategory::factory()->create(['user_id' => $this->user->id, 'category_id' => $this->cars->id]);
    $this->cars->forceFill(['is_active' => false])->save();
    app(CatalogCache::class)->taxonomyChanged();

    putJson('/api/v1/account/favorite-categories/cars')->assertNotFound()->assertJsonPath('error.code', 'CAT_001');
    expect(getJson('/api/v1/account/favorite-categories')->json('data'))->toBe([]);

    deleteJson('/api/v1/account/favorite-categories/cars')->assertOk();
    expect(FavoriteCategory::query()->count())->toBe(0);
});

it('stops at the per-user limit with FAV_002 but still accepts a repeat', function (): void {
    config(['qbazaar.favorites.max_categories_per_user' => 1]);
    putJson('/api/v1/account/favorite-categories/cars')->assertOk();

    putJson('/api/v1/account/favorite-categories/motorcycles')->assertStatus(422)->assertJsonPath('error.code', 'FAV_002');
    putJson('/api/v1/account/favorite-categories/cars')->assertOk();
});

it('rejects a malformed slug', function (): void {
    putJson('/api/v1/account/favorite-categories/' . str_repeat('a', 121))->assertStatus(422);
    putJson('/api/v1/account/favorite-categories/bad_slug!')->assertStatus(422);
});

it('keeps favourites private to their owner', function (): void {
    FavoriteCategory::factory()->create(['category_id' => $this->cars->id]);

    expect(getJson('/api/v1/account/favorite-categories')->json('data'))->toBe([]);

    deleteJson('/api/v1/account/favorite-categories/cars')->assertOk();
    expect(FavoriteCategory::query()->count())->toBe(1);
});

it('requires authentication', function (): void {
    $this->app['auth']->forgetGuards();

    getJson('/api/v1/account/favorite-categories')->assertUnauthorized();
    putJson('/api/v1/account/favorite-categories/cars')->assertUnauthorized();
    deleteJson('/api/v1/account/favorite-categories/cars')->assertUnauthorized();
});

it('flags is_favorited on the category page for the caller only', function (): void {
    expect(getJson('/api/v1/categories/cars')->assertOk()->json('data.is_favorited'))->toBeFalse();

    putJson('/api/v1/account/favorite-categories/cars')->assertOk();

    expect(getJson('/api/v1/categories/cars')->json('data.is_favorited'))->toBeTrue();

    Sanctum::actingAs(User::factory()->create(), ['*']);
    expect(getJson('/api/v1/categories/cars')->json('data.is_favorited'))->toBeFalse();
});

it('reports false to guests', function (): void {
    $this->app['auth']->forgetGuards();

    getJson('/api/v1/categories/cars')->assertOk()->assertJsonPath('data.is_favorited', false);
});
