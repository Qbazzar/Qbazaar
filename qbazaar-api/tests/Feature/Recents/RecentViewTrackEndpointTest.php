<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\RecentView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
    $this->ad = $this->makeAd($this->seller, [
        'status' => 'active',
        'published_at' => now(),
        'views_count' => 0,
    ]);
    // Use the array cache so the in-process lock TTL controls the throttle
    // window in tests deterministically.
    Cache::flush();
});

it('records a view for an anonymous client identified by X-Session-Id', function (): void {
    postJson(
        "/api/v1/ads/{$this->ad->id}/view",
        [],
        ['Accept' => 'application/json', 'X-Session-Id' => 'sess-abc-123'],
    )->assertNoContent();

    expect(RecentView::query()->where('session_id', 'sess-abc-123')->where('ad_id', $this->ad->id)->count())->toBe(1)
        ->and((int) Ad::query()->where('id', $this->ad->id)->value('views_count'))->toBe(1);
});

it('records a view for an authenticated user', function (): void {
    $viewer = User::factory()->create();
    Sanctum::actingAs($viewer, ['*']);

    postJson("/api/v1/ads/{$this->ad->id}/view", [], ['Accept' => 'application/json'])
        ->assertNoContent();

    expect(RecentView::query()->where('user_id', $viewer->id)->where('ad_id', $this->ad->id)->count())->toBe(1)
        ->and((int) Ad::query()->where('id', $this->ad->id)->value('views_count'))->toBe(1);
});

it('throttles repeat views from the same viewer to one row per hour', function (): void {
    $viewer = User::factory()->create();
    Sanctum::actingAs($viewer, ['*']);

    postJson("/api/v1/ads/{$this->ad->id}/view", [], ['Accept' => 'application/json'])
        ->assertNoContent();
    postJson("/api/v1/ads/{$this->ad->id}/view", [], ['Accept' => 'application/json'])
        ->assertNoContent();

    expect(RecentView::query()->where('user_id', $viewer->id)->where('ad_id', $this->ad->id)->count())->toBe(1)
        ->and((int) Ad::query()->where('id', $this->ad->id)->value('views_count'))->toBe(1);
});

it('records again after the throttle window elapses', function (): void {
    $viewer = User::factory()->create();
    Sanctum::actingAs($viewer, ['*']);

    postJson("/api/v1/ads/{$this->ad->id}/view", [], ['Accept' => 'application/json'])
        ->assertNoContent();

    // Simulate the hour-long lock expiring by flushing the lock store.
    Cache::flush();

    postJson("/api/v1/ads/{$this->ad->id}/view", [], ['Accept' => 'application/json'])
        ->assertNoContent();

    expect(RecentView::query()->where('user_id', $viewer->id)->where('ad_id', $this->ad->id)->count())->toBe(2)
        ->and((int) Ad::query()->where('id', $this->ad->id)->value('views_count'))->toBe(2);
});

it('returns 204 when neither X-Session-Id nor an auth token is supplied (silent no-op)', function (): void {
    postJson("/api/v1/ads/{$this->ad->id}/view", [], ['Accept' => 'application/json'])
        ->assertNoContent();

    expect(RecentView::query()->count())->toBe(0)
        ->and((int) Ad::query()->where('id', $this->ad->id)->value('views_count'))->toBe(0);
});

it('returns AD_NOT_FOUND when the ad does not exist', function (): void {
    postJson(
        '/api/v1/ads/01HZZZZZZZZZZZZZZZZZZZZZZZ/view',
        [],
        ['Accept' => 'application/json', 'X-Session-Id' => 'sess-xyz'],
    )
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'AD_001');
});
