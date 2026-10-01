<?php

declare(strict_types=1);

use App\Actions\Ads\ToggleAdFeaturedAction;
use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    Cache::flush();
    $this->seller = User::factory()->create();
    $this->cars = Category::query()->where('slug', 'cars')->firstOrFail();
});

it('serves edited custom fields and filters right after a category change', function (): void {
    getJson('/api/v1/categories/cars/fields')->assertOk();
    getJson('/api/v1/categories/cars/filters')->assertOk();

    $this->cars->update([
        'custom_fields' => [['key' => 'trim', 'type' => 'text', 'label' => ['ar' => 'الفئة', 'en' => 'Trim'], 'required' => false]],
        'custom_filters' => [['key' => 'trim', 'type' => 'text', 'label' => ['ar' => 'الفئة', 'en' => 'Trim']]],
    ]);

    expect(getJson('/api/v1/categories/cars/fields')->assertOk()->json('data.*.key'))->toBe(['trim'])
        ->and(getJson('/api/v1/categories/cars/filters')->assertOk()->json('data.*.key'))->toBe(['trim']);
});

it('stops serving the old slug once a category is renamed', function (): void {
    getJson('/api/v1/categories/cars/fields')->assertOk();

    $this->cars->update(['slug' => 'automobiles']);

    getJson('/api/v1/categories/cars/fields')->assertNotFound();
    getJson('/api/v1/categories/automobiles/fields')->assertOk();
});

it('answers repeated field requests from the cache', function (): void {
    getJson('/api/v1/categories/cars/fields')->assertOk();

    DB::enableQueryLog();
    getJson('/api/v1/categories/cars/fields')->assertOk();
    DB::disableQueryLog();

    $categoryQueries = array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'categories'));

    expect($categoryQueries)->toBe([]);
});

it('shows a newly featured ad without waiting for the cache to expire', function (): void {
    $ad = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'featured' => false]);
    expect(getJson('/api/v1/ads/featured')->assertOk()->json('data'))->toBe([]);

    app(ToggleAdFeaturedAction::class)($ad);

    expect(getJson('/api/v1/ads/featured')->json('data.*.id'))->toBe([$ad->id]);
});

it('drops a featured ad from the list once it leaves the market', function (): void {
    $ad = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'featured' => true]);
    expect(getJson('/api/v1/ads/featured')->json('data.*.id'))->toBe([$ad->id]);

    $ad->forceFill(['status' => AdStatus::SOLD])->save();

    expect(getJson('/api/v1/ads/featured')->json('data'))->toBe([]);
});

it('hides similar ads of an ad the caller cannot see', function (): void {
    $draft = Ad::factory()->draft()->create(['user_id' => $this->seller->id]);

    getJson("/api/v1/ads/{$draft->id}/similar")->assertNotFound();

    Sanctum::actingAs($this->seller, ['*']);
    getJson("/api/v1/ads/{$draft->id}/similar")->assertOk();
});

it('keeps similar ads available for a sold ad, without listing sold ones', function (): void {
    $sold = Ad::factory()->sold()->create(['user_id' => $this->seller->id, 'category_id' => $this->cars->id]);
    $live = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'category_id' => $this->cars->id]);
    Ad::factory()->sold()->create(['user_id' => $this->seller->id, 'category_id' => $this->cars->id]);

    expect(getJson("/api/v1/ads/{$sold->id}/similar")->assertOk()->json('data.*.id'))->toBe([$live->id]);
});
