<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->user = User::factory()->create();
});

it('submits a draft for review (pending) and keeps it off the public feed', function (): void {
    Sanctum::actingAs($this->user, ['*']);

    $ad = $this->makeAd($this->user, ['status' => AdStatus::DRAFT->value]);

    $response = postJson("/api/v1/ads/{$ad->id}/publish", [], [
        'Accept' => 'application/json',
    ]);

    $response->assertOk()
        ->assertJson(
            fn ($json) => $json
                ->where('success', true)
                ->where('data.status', AdStatus::PENDING->value)
                ->etc(),
        );

    // Stays hidden until an admin approves it.
    getJson('/api/v1/ads', ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('notifies reviewers via the panel bell when an ad is submitted', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $reviewer = User::factory()->create();
    $reviewer->assignRole('super_admin');

    Sanctum::actingAs($this->user, ['*']);
    $ad = $this->makeAd($this->user, ['status' => AdStatus::DRAFT->value]);

    postJson("/api/v1/ads/{$ad->id}/publish", [], ['Accept' => 'application/json'])
        ->assertOk();

    expect($reviewer->fresh()->notifications()->count())->toBe(1);
});

it('refuses to publish someone else\'s draft', function (): void {
    $intruder = User::factory()->create();
    Sanctum::actingAs($intruder, ['*']);

    $ad = $this->makeAd($this->user, ['status' => AdStatus::DRAFT->value]);

    postJson("/api/v1/ads/{$ad->id}/publish", [], [
        'Accept' => 'application/json',
    ])->assertStatus(403);
});
