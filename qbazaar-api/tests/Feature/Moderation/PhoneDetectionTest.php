<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Events\Ads\AdSubmittedForReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);
});

// Publish now always parks the ad in PENDING for manual review; assert the
// phone rule fired via the AdSubmittedForReview event's ModerationResult.

it('flags an ad whose description contains a Qatari phone number', function (): void {
    Event::fake([AdSubmittedForReview::class]);

    $ad = $this->makeAd($this->user, [
        'status' => AdStatus::DRAFT->value,
        'title' => 'Bicycle in great shape — barely used',
        'description' => 'Selling my bicycle, please call me on +97455123456 anytime to arrange a viewing in Al Sadd.',
    ]);

    postJson("/api/v1/ads/{$ad->id}/publish", [], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::PENDING->value);

    Event::assertDispatched(
        AdSubmittedForReview::class,
        fn (AdSubmittedForReview $e): bool => in_array('phone', $e->result->flags, true),
    );
});

it('flags an ad whose description contains a bare 8-digit number', function (): void {
    Event::fake([AdSubmittedForReview::class]);

    $ad = $this->makeAd($this->user, [
        'status' => AdStatus::DRAFT->value,
        'title' => 'Sofa set for the living room',
        'description' => 'Beautiful sofa set in pristine condition. WhatsApp 55123456 for fast response and pickup details.',
    ]);

    postJson("/api/v1/ads/{$ad->id}/publish", [], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::PENDING->value);

    Event::assertDispatched(
        AdSubmittedForReview::class,
        fn (AdSubmittedForReview $e): bool => in_array('phone', $e->result->flags, true),
    );
});
