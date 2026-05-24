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

it('lets anonymous callers see active ads', function (): void {
    $ad = $this->makeAd($this->seller, [
        'status' => AdStatus::ACTIVE->value,
        'published_at' => now(),
    ]);

    getJson("/api/v1/ads/{$ad->id}", ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.id', $ad->id)
        ->assertJsonPath('data.status', AdStatus::ACTIVE->value);
});

it('hides drafts from anonymous callers (404)', function (): void {
    $ad = $this->makeAd($this->seller, ['status' => AdStatus::DRAFT->value]);

    getJson("/api/v1/ads/{$ad->id}", ['Accept' => 'application/json'])
        ->assertStatus(404);
});

it('lets the owner see their own draft', function (): void {
    Sanctum::actingAs($this->seller, ['*']);

    $ad = $this->makeAd($this->seller, ['status' => AdStatus::DRAFT->value]);

    getJson("/api/v1/ads/{$ad->id}", ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.id', $ad->id)
        ->assertJsonPath('data.status', AdStatus::DRAFT->value);
});
