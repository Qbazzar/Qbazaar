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

it('marks an active ad as sold', function (): void {
    Sanctum::actingAs($this->seller, ['*']);

    $ad = $this->makeAd($this->seller, [
        'status' => AdStatus::ACTIVE->value,
        'published_at' => now()->subDay(),
        'expires_at' => now()->addDays(29),
    ]);

    postJson("/api/v1/ads/{$ad->id}/mark-sold", [], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::SOLD->value);
});

it('refuses to mark a draft as sold', function (): void {
    Sanctum::actingAs($this->seller, ['*']);

    $ad = $this->makeAd($this->seller, ['status' => AdStatus::DRAFT->value]);

    postJson("/api/v1/ads/{$ad->id}/mark-sold", [], [
        'Accept' => 'application/json',
    ])->assertStatus(403);
});
