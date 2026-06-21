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
// external-link rule fired via the AdSubmittedForReview ModerationResult.

it('flags an ad whose description contains an external https link', function (): void {
    Event::fake([AdSubmittedForReview::class]);

    $ad = $this->makeAd($this->user, [
        'status' => AdStatus::DRAFT->value,
        'title' => 'Brand new laptop available for sale today',
        'description' => 'Brand new sealed laptop. See full specs at https://my-personal-shop.example.com/listing and contact via the site.',
    ]);

    postJson("/api/v1/ads/{$ad->id}/publish", [], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::PENDING->value);

    Event::assertDispatched(
        AdSubmittedForReview::class,
        fn (AdSubmittedForReview $e): bool => in_array('external_link', $e->result->flags, true),
    );
});

it('flags an ad whose description contains a bare www link', function (): void {
    Event::fake([AdSubmittedForReview::class]);

    $ad = $this->makeAd($this->user, [
        'status' => AdStatus::DRAFT->value,
        'title' => 'Office chair in excellent shape',
        'description' => 'Ergonomic office chair from our showroom — visit us at www.someshop.qa for more pictures and price.',
    ]);

    postJson("/api/v1/ads/{$ad->id}/publish", [], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::PENDING->value);

    Event::assertDispatched(
        AdSubmittedForReview::class,
        fn (AdSubmittedForReview $e): bool => in_array('external_link', $e->result->flags, true),
    );
});
