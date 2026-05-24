<?php

declare(strict_types=1);

use App\Models\RecentView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);
    $this->seller = User::factory()->create();
});

it('clears the caller recently-viewed rows and leaves other users untouched', function (): void {
    $other = User::factory()->create();
    $ad = $this->makeAd($this->seller, ['status' => 'active', 'published_at' => now()]);

    RecentView::query()->create([
        'user_id' => $this->user->id,
        'ad_id' => $ad->id,
        'viewed_at' => now()->subMinutes(10),
    ]);
    RecentView::query()->create([
        'user_id' => $this->user->id,
        'ad_id' => $ad->id,
        'viewed_at' => now()->subMinutes(5),
    ]);
    RecentView::query()->create([
        'user_id' => $other->id,
        'ad_id' => $ad->id,
        'viewed_at' => now(),
    ]);

    deleteJson('/api/v1/account/recently-viewed', [], ['Accept' => 'application/json'])
        ->assertNoContent();

    expect(RecentView::query()->where('user_id', $this->user->id)->count())->toBe(0)
        ->and(RecentView::query()->where('user_id', $other->id)->count())->toBe(1);
});
