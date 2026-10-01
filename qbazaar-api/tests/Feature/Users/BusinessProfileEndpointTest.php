<?php

declare(strict_types=1);

use App\Data\Account\PrivacySettings;
use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->business = User::factory()->business()->create(['full_name' => 'Ahmad Trading']);

    $this->profile = [
        'business_name' => 'Doha Electronics',
        'about' => 'Phones, laptops and accessories since 2010.',
        'legal_name' => 'Doha Electronics W.L.L.',
        'commercial_registration_number' => 'CR-12345',
        'contact_phone' => '+97455123456',
        'contact_email' => 'sales@doha-electronics.qa',
        'website' => 'https://doha-electronics.qa',
        'address' => 'Salwa Road, Doha',
        'opening_hours' => [
            ['day' => 'sat', 'open' => '09:00', 'close' => '22:00'],
            ['day' => 'fri', 'closed' => true],
        ],
    ];
});

it('lets a business account create and update its profile', function (): void {
    Sanctum::actingAs($this->business, ['*']);

    putJson('/api/v1/account/business-profile', $this->profile)
        ->assertOk()
        ->assertJsonPath('data.business_name', 'Doha Electronics')
        ->assertJsonPath('data.contact_phone', '+97455123456')
        ->assertJsonPath('data.opening_hours.0', ['day' => 'sat', 'closed' => false, 'open' => '09:00', 'close' => '22:00'])
        ->assertJsonPath('data.opening_hours.1', ['day' => 'fri', 'closed' => true, 'open' => null, 'close' => null]);

    putJson('/api/v1/account/business-profile', ['about' => 'Now with repairs.'])
        ->assertOk()
        ->assertJsonPath('data.about', 'Now with repairs.')
        ->assertJsonPath('data.business_name', 'Doha Electronics');

    getJson('/api/v1/account/business-profile')->assertOk()->assertJsonPath('data.about', 'Now with repairs.');

    expect(BusinessProfile::query()->count())->toBe(1)
        ->and(Activity::query()->where('event', 'business_profile_updated')->where('causer_id', $this->business->id)->count())->toBe(2);
});

it('refuses a business profile on a private account', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    putJson('/api/v1/account/business-profile', $this->profile)
        ->assertForbidden()
        ->assertJsonPath('error.code', 'BIZ_001');
    getJson('/api/v1/account/business-profile')->assertForbidden()->assertJsonPath('error.code', 'BIZ_001');

    expect(BusinessProfile::query()->count())->toBe(0);
});

it('validates the profile fields', function (): void {
    Sanctum::actingAs($this->business, ['*']);

    putJson('/api/v1/account/business-profile', [
        'about' => '<script>alert(1)</script>',
        'contact_phone' => '12345',
        'contact_email' => 'not-an-email',
        'website' => 'javascript:alert(1)',
        'opening_hours' => [
            ['day' => 'sat', 'open' => '9am'],
            ['day' => 'sat', 'closed' => true],
            ['day' => 'someday', 'closed' => true],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'about',
            'contact_phone',
            'contact_email',
            'website',
            'opening_hours.0.open',
            'opening_hours.0.close',
            'opening_hours.1.day',
            'opening_hours.2.day',
        ], 'error.details');
});

it('requires authentication', function (): void {
    putJson('/api/v1/account/business-profile', $this->profile)->assertUnauthorized();
});

it('shows the business profile on the public profile and respects privacy', function (): void {
    $this->business->businessProfile()->create($this->profile);
    $this->business->forceFill(['privacy_settings' => new PrivacySettings(show_phone: false, show_email: false)])->save();

    getJson("/api/v1/users/{$this->business->id}/public-profile")
        ->assertOk()
        ->assertJsonPath('data.business_name', 'Doha Electronics')
        ->assertJsonPath('data.business_profile.legal_name', 'Doha Electronics W.L.L.')
        ->assertJsonPath('data.business_profile.commercial_registration_number', 'CR-12345')
        ->assertJsonPath('data.business_profile.contact_phone', null)
        ->assertJsonPath('data.business_profile.contact_email', null)
        ->assertJsonPath('data.business_profile.website', 'https://doha-electronics.qa');

    $this->business->forceFill(['privacy_settings' => new PrivacySettings(show_phone: true, show_email: true)])->save();

    getJson("/api/v1/users/{$this->business->id}/public-profile")
        ->assertOk()
        ->assertJsonPath('data.business_profile.contact_phone', '+97455123456')
        ->assertJsonPath('data.business_profile.contact_email', 'sales@doha-electronics.qa');
});

it('has no business profile block for private accounts', function (): void {
    $private = User::factory()->create();

    getJson("/api/v1/users/{$private->id}/public-profile")
        ->assertOk()
        ->assertJsonPath('data.business_profile', null)
        ->assertJsonPath('data.business_name', null);
});

it('uploads, replaces and removes the cover image', function (): void {
    Sanctum::actingAs($this->business, ['*']);

    postJson('/api/v1/account/business-profile/cover', ['cover' => UploadedFile::fake()->image('cover.jpg', 1600, 600)])
        ->assertOk()
        ->assertJsonPath('data.cover_url', fn (?string $url): bool => is_string($url) && $url !== '');

    postJson('/api/v1/account/business-profile/cover', ['cover' => UploadedFile::fake()->image('cover2.jpg', 1600, 600)])
        ->assertOk();

    expect($this->business->fresh()?->getMedia(User::BUSINESS_COVER_COLLECTION))->toHaveCount(1);

    getJson("/api/v1/users/{$this->business->id}/public-profile")
        ->assertOk()
        ->assertJsonPath('data.business_profile.cover_url', fn (?string $url): bool => is_string($url) && $url !== '');

    deleteJson('/api/v1/account/business-profile/cover')
        ->assertOk()
        ->assertJsonPath('data.cover_url', null);
});

it('rejects a cover that is not an image', function (): void {
    Sanctum::actingAs($this->business, ['*']);

    postJson('/api/v1/account/business-profile/cover', ['cover' => UploadedFile::fake()->create('cover.pdf', 10, 'application/pdf')])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cover'], 'error.details');
});
