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

// Every ad now enters PENDING on publish (manual admin review), so the
// moderation outcome rides the AdSubmittedForReview event's ModerationResult
// rather than the resulting status — assert the rule there.

it('flags an ad in moderation when the description contains a banned word', function (): void {
    Event::fake([AdSubmittedForReview::class]);

    $ad = $this->makeAd($this->user, [
        'status' => AdStatus::DRAFT->value,
        'title' => 'Great phone for sale',
        'description' => 'Buy this phone today! Bitcoin payment accepted. ' . str_repeat('Lorem ipsum text. ', 5),
    ]);

    postJson("/api/v1/ads/{$ad->id}/publish", [], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::PENDING->value);

    Event::assertDispatched(
        AdSubmittedForReview::class,
        fn (AdSubmittedForReview $e): bool => ! $e->result->clean
            && in_array('banned_words', $e->result->flags, true),
    );
});

it('passes moderation cleanly for a clean ad', function (): void {
    Event::fake([AdSubmittedForReview::class]);

    $ad = $this->makeAd($this->user, [
        'status' => AdStatus::DRAFT->value,
        'title' => 'Comfy reading chair for the living room',
        'description' => 'Very comfortable reading chair in excellent condition. Wood frame and cotton upholstery. Pick up from West Bay.',
    ]);

    postJson("/api/v1/ads/{$ad->id}/publish", [], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::PENDING->value);

    Event::assertDispatched(
        AdSubmittedForReview::class,
        fn (AdSubmittedForReview $e): bool => $e->result->clean,
    );
});
