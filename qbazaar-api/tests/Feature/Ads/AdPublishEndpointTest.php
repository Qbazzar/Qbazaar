<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\PlatformSetting;
use App\Events\Ads\AdSubmittedForReview;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->user = User::factory()->phoneVerified()->create();
});

it('submits a draft for review (pending) and keeps it off the public feed', function (): void {
    Sanctum::actingAs($this->user, ['*']);

    $ad = $this->makePublishableDraft($this->user);

    $response = postJson("/api/v1/ads/{$ad->id}/publish", ['accepted_terms' => true], [
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
    $reviewer = User::factory()->phoneVerified()->create();
    $reviewer->assignRole('super_admin');

    Sanctum::actingAs($this->user, ['*']);
    $ad = $this->makePublishableDraft($this->user);

    postJson("/api/v1/ads/{$ad->id}/publish", ['accepted_terms' => true], ['Accept' => 'application/json'])
        ->assertOk();

    expect($reviewer->fresh()->notifications()->count())->toBe(1);
});

it('refuses to publish someone else\'s draft', function (): void {
    $intruder = User::factory()->phoneVerified()->create();
    Sanctum::actingAs($intruder, ['*']);

    $ad = $this->makePublishableDraft($this->user);

    postJson("/api/v1/ads/{$ad->id}/publish", ['accepted_terms' => true], [
        'Accept' => 'application/json',
    ])->assertStatus(403);
});

it('requires the seller to accept the terms', function (mixed $acceptedTerms): void {
    Sanctum::actingAs($this->user, ['*']);
    $ad = $this->makePublishableDraft($this->user);

    postJson("/api/v1/ads/{$ad->id}/publish", array_filter(['accepted_terms' => $acceptedTerms], fn ($v) => $v !== null))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['error' => ['details' => ['accepted_terms']]]);

    expect($ad->fresh()->status)->toBe(AdStatus::DRAFT);
})->with(['missing' => null, 'declined' => false]);

it('refuses to publish an ad with fewer images than the minimum', function (): void {
    Sanctum::actingAs($this->user, ['*']);
    config(['qbazaar.ads.min_images' => 2]);

    $ad = $this->makeAd($this->user, ['status' => AdStatus::DRAFT->value]);
    $this->attachImageRows($ad, 1);

    postJson("/api/v1/ads/{$ad->id}/publish", ['accepted_terms' => true])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'AD_009')
        ->assertJsonPath('error.details', ['min' => 2, 'current' => 1]);

    expect($ad->fresh()->status)->toBe(AdStatus::DRAFT);
});

it('enforces the daily publish limit from the platform settings', function (): void {
    Sanctum::actingAs($this->user, ['*']);
    app(SettingsService::class)->put(PlatformSetting::AD_DAILY_PUBLISH_LIMIT, 1, $this->user);

    $first = $this->makePublishableDraft($this->user);
    $second = $this->makePublishableDraft($this->user);

    postJson("/api/v1/ads/{$first->id}/publish", ['accepted_terms' => true])->assertOk();

    postJson("/api/v1/ads/{$second->id}/publish", ['accepted_terms' => true])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'AD_006');

    expect($second->fresh()->status)->toBe(AdStatus::DRAFT);

    $this->travel(25)->hours();

    postJson("/api/v1/ads/{$second->id}/publish", ['accepted_terms' => true])->assertOk();
});

it('treats publishing an ad already under review as a no-op', function (): void {
    Sanctum::actingAs($this->user, ['*']);
    $ad = $this->makePublishableDraft($this->user);

    postJson("/api/v1/ads/{$ad->id}/publish", ['accepted_terms' => true])->assertOk();
    $submittedAt = $ad->fresh()->submitted_at;

    Event::fake([AdSubmittedForReview::class]);
    $this->travel(5)->minutes();

    postJson("/api/v1/ads/{$ad->id}/publish", ['accepted_terms' => true])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::PENDING->value);

    Event::assertNotDispatched(AdSubmittedForReview::class);
    expect($ad->fresh()->submitted_at?->equalTo($submittedAt))->toBeTrue();
});

it('resubmits a rejected ad for review', function (): void {
    Sanctum::actingAs($this->user, ['*']);
    $ad = $this->makePublishableDraft($this->user, ['status' => AdStatus::REJECTED->value]);

    postJson("/api/v1/ads/{$ad->id}/publish", ['accepted_terms' => true])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::PENDING->value);
});

it('does not count draft creation against the publish limiter', function (): void {
    Sanctum::actingAs($this->user, ['*']);
    config(['qbazaar.ads.publish_attempts_per_minute_per_user' => 1]);

    $payload = [
        'title' => 'Wooden dining table for six',
        'description' => 'Solid wood dining table, seats six people comfortably, minor scratches.',
        'category_id' => Category::query()->whereNull('custom_fields')->value('id'),
        'location_id' => Location::query()->value('id'),
        'price' => 750,
        'price_type' => 'fixed',
        'condition' => 'used',
    ];

    postJson('/api/v1/ads', $payload)->assertCreated();
    postJson('/api/v1/ads', $payload)->assertCreated();

    $ad = $this->makePublishableDraft($this->user);

    postJson("/api/v1/ads/{$ad->id}/publish", ['accepted_terms' => true])->assertOk();
});
