<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
});

it('renews an expired ad back to active and extends expiry', function (): void {
    Sanctum::actingAs($this->seller, ['*']);

    $ad = $this->makeAd($this->seller, [
        'status' => AdStatus::EXPIRED->value,
        'published_at' => now()->subDays(60),
        'expires_at' => now()->subDay(),
    ]);

    $response = postJson("/api/v1/ads/{$ad->id}/renew", [], [
        'Accept' => 'application/json',
    ])->assertOk();

    expect($response->json('data.status'))->toBe(AdStatus::ACTIVE->value);

    $fresh = $ad->fresh();
    expect($fresh)->not->toBeNull();
    /** @phpstan-ignore-next-line */
    expect($fresh->expires_at->isFuture())->toBeTrue();
});
