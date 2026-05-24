<?php

declare(strict_types=1);

use App\Actions\Recents\TrackAdViewAction;
use App\Models\RecentView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);
    $this->seller = User::factory()->create();
});

it('lists the caller history newest first with viewed_at injected', function (): void {
    $adA = $this->makeAd($this->seller, ['status' => 'active', 'published_at' => now()]);
    $adB = $this->makeAd($this->seller, ['status' => 'active', 'published_at' => now()]);

    RecentView::query()->create([
        'user_id' => $this->user->id,
        'ad_id' => $adA->id,
        'viewed_at' => now()->subHours(3),
    ]);
    RecentView::query()->create([
        'user_id' => $this->user->id,
        'ad_id' => $adB->id,
        'viewed_at' => now()->subMinutes(15),
    ]);

    $response = getJson('/api/v1/account/recently-viewed', ['Accept' => 'application/json'])
        ->assertOk();

    $data = $response->json('data');

    expect($data)->toHaveCount(2)
        ->and($data[0]['id'])->toBe($adB->id)
        ->and($data[1]['id'])->toBe($adA->id)
        ->and($data[0])->toHaveKey('viewed_at');
});

it('caps the stored history at 50 rows per user — the 51st insert evicts the oldest', function (): void {
    $ad = $this->makeAd($this->seller, ['status' => 'active', 'published_at' => now()]);

    // Seed 50 existing rows with strictly increasing viewed_at so the
    // ordering of eviction is deterministic.
    for ($i = 0; $i < 50; $i++) {
        RecentView::query()->create([
            'user_id' => $this->user->id,
            'ad_id' => $ad->id,
            'viewed_at' => now()->subMinutes(60 - $i),
        ]);
    }

    $oldest = RecentView::query()
        ->where('user_id', $this->user->id)
        ->orderBy('viewed_at')
        ->first();

    expect($oldest)->not->toBeNull();

    // Insert one more (the cap trigger) — call the action directly so we
    // don't hit the per-hour throttle.
    /** @var TrackAdViewAction $action */
    $action = app(TrackAdViewAction::class);
    $action->execute($ad, $this->user, null);

    expect(RecentView::query()->where('user_id', $this->user->id)->count())->toBe(50)
        ->and(RecentView::query()->where('id', $oldest->id)->exists())->toBeFalse();
});
