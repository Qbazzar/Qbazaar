<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->target = User::factory()->create();
});

it('returns only the user\'s active ads', function (): void {
    $active = $this->makeAd($this->target, [
        'status' => AdStatus::ACTIVE->value,
        'published_at' => now(),
    ]);

    // Non-public statuses must never surface on a public profile feed.
    $this->makeAd($this->target, ['status' => AdStatus::DRAFT->value]);
    $this->makeAd($this->target, ['status' => AdStatus::SOLD->value]);

    // Another seller's active ad must not leak into this user's list.
    $this->makeAd(User::factory()->create(), [
        'status' => AdStatus::ACTIVE->value,
        'published_at' => now(),
    ]);

    $response = getJson('/api/v1/users/' . $this->target->id . '/ads')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($active->id);

    $response
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.has_more', false);
});

it('returns 404 when the user is not active', function (): void {
    $this->target->forceFill(['status' => UserStatus::SUSPENDED->value])->save();

    getJson('/api/v1/users/' . $this->target->id . '/ads')
        ->assertStatus(404);
});
