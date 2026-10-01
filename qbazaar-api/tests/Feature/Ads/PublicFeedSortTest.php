<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
});

it('sorts the public feed by views, newest first on ties', function (): void {
    $quiet = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'views_count' => 3, 'published_at' => now()->subDay()]);
    $popular = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'views_count' => 90, 'published_at' => now()->subDays(3)]);
    $quietNewer = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'views_count' => 3, 'published_at' => now()]);

    expect(getJson('/api/v1/ads?sort=most_viewed')->assertOk()->json('data.*.id'))
        ->toBe([$popular->id, $quietNewer->id, $quiet->id]);
});

it('rejects an unknown or search-only sort on the feed', function (string $sort): void {
    getJson("/api/v1/ads?sort={$sort}")->assertStatus(422);
})->with(['banana', 'distance']);

it('rejects a price range whose maximum is below its minimum', function (): void {
    getJson('/api/v1/ads?price_min=500&price_max=100')->assertStatus(422);
});

it('has the indexes the listing, feed and lookup queries rely on', function (string $table, string $index): void {
    expect(collect(Schema::getIndexes($table))->pluck('name'))->toContain($index);
})->with([
    ['ads', 'ads_status_price_idx'],
    ['ads', 'ads_status_views_idx'],
    ['ads', 'ads_status_updated_idx'],
    ['notifications', 'notifications_notifiable_created_idx'],
    ['users', 'users_last_login_at_idx'],
]);
