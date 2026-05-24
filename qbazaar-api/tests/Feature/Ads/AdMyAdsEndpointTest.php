<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
});

it('returns the caller\'s ads across every status', function (): void {
    Sanctum::actingAs($this->seller, ['*']);

    $other = User::factory()->create();

    $this->makeAd($this->seller, ['status' => AdStatus::DRAFT->value]);
    $this->makeAd($this->seller, [
        'status' => AdStatus::ACTIVE->value,
        'published_at' => now(),
    ]);
    $this->makeAd($this->seller, ['status' => AdStatus::SOLD->value]);
    $this->makeAd($other, ['status' => AdStatus::ACTIVE->value]);

    $response = getJson('/api/v1/account/ads', [
        'Accept' => 'application/json',
    ])->assertOk();

    expect($response->json('data'))->toHaveCount(3);
});

it('requires authentication', function (): void {
    getJson('/api/v1/account/ads', [
        'Accept' => 'application/json',
    ])->assertStatus(401);
});
