<?php

declare(strict_types=1);

use App\Data\Account\PrivacySettings;
use App\Enums\AdStatus;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->target = User::factory()->create([
        'full_name' => 'Public Alice',
        'email_verified' => true,
        'phone_verified' => false,
    ]);
});

it('returns the public profile without auth', function (): void {
    getJson('/api/v1/users/' . $this->target->id . '/public-profile')
        ->assertOk()
        ->assertJson(
            fn ($json) => $json
                ->where('success', true)
                ->where('data.id', $this->target->id)
                ->where('data.full_name', 'Public Alice')
                ->where('data.verification_badges.email_verified', true)
                ->where('data.verification_badges.phone_verified', false)
                ->where('data.ads_count', 0)
                ->etc(),
        );
});

it('counts only the target active ads', function (): void {
    $this->seedReferenceData();

    $this->makeAd($this->target, ['status' => AdStatus::ACTIVE->value, 'published_at' => now()]);
    $this->makeAd($this->target, ['status' => AdStatus::ACTIVE->value, 'published_at' => now()]);
    // Draft and sold listings are not public, so they must not be counted.
    $this->makeAd($this->target, ['status' => AdStatus::DRAFT->value]);
    $this->makeAd($this->target, ['status' => AdStatus::SOLD->value]);
    // Another user's active ad must not inflate this profile's count.
    $this->makeAd(User::factory()->create(), ['status' => AdStatus::ACTIVE->value, 'published_at' => now()]);

    getJson('/api/v1/users/' . $this->target->id . '/public-profile')
        ->assertOk()
        ->assertJsonPath('data.ads_count', 2);
});

it('exposes phone only when show_phone is true', function (): void {
    getJson('/api/v1/users/' . $this->target->id . '/public-profile')
        ->assertOk()
        ->assertJson(
            fn ($json) => $json
                ->has('data.phone')
                ->etc(),
        );

    $this->target->forceFill([
        'privacy_settings' => new PrivacySettings(show_phone: false),
    ])->save();

    getJson('/api/v1/users/' . $this->target->id . '/public-profile')
        ->assertOk()
        ->assertJson(
            fn ($json) => $json
                ->missing('data.phone')
                ->etc(),
        );
});

it('exposes email only when show_email is true', function (): void {
    // Default show_email is false
    getJson('/api/v1/users/' . $this->target->id . '/public-profile')
        ->assertOk()
        ->assertJson(
            fn ($json) => $json
                ->missing('data.email')
                ->etc(),
        );

    $this->target->forceFill([
        'privacy_settings' => new PrivacySettings(show_email: true),
    ])->save();

    getJson('/api/v1/users/' . $this->target->id . '/public-profile')
        ->assertOk()
        ->assertJson(
            fn ($json) => $json
                ->has('data.email')
                ->etc(),
        );
});

it('returns 404 when the user is not active', function (): void {
    $this->target->forceFill(['status' => UserStatus::SUSPENDED->value])->save();

    getJson('/api/v1/users/' . $this->target->id . '/public-profile')
        ->assertStatus(404)
        ->assertJson(
            fn ($json) => $json
                ->where('error.code', 'USER_001')
                ->etc(),
        );
});

it('returns 404 for a nonexistent user', function (): void {
    getJson('/api/v1/users/01HZZZZZZZZZZZZZZZZZZZZZZZ/public-profile')
        ->assertStatus(404);
});
