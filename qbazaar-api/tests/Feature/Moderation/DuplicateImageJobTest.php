<?php

declare(strict_types=1);

use App\Data\Moderation\ModerationResult;
use App\Enums\AdStatus;
use App\Jobs\Ads\DetectDuplicateImagesJob;
use App\Jobs\ProcessAdImagesJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->seedReferenceData();
    $this->seller = User::factory()->phoneVerified()->create();
});

it('leaves the image scan to the queue and stores the text checks on the ad', function (): void {
    Queue::fake([DetectDuplicateImagesJob::class]);
    Sanctum::actingAs($this->seller, ['*']);

    $draft = $this->makePublishableDraft($this->seller, [
        'title' => 'Reading chair, call me',
        'description' => 'Comfortable reading chair in excellent condition. Call 55123456 for details.',
    ]);

    postJson("/api/v1/ads/{$draft->id}/publish", ['accepted_terms' => true])->assertOk();

    Queue::assertPushedOn('low', DetectDuplicateImagesJob::class, fn (DetectDuplicateImagesJob $job): bool => $job->adId === $draft->id);

    $result = $draft->fresh()?->moderation_result;
    expect($result)->toBeInstanceOf(ModerationResult::class)
        ->and($result?->flags)->toBe(['phone']);
});

it('replaces a stale duplicate finding and keeps the text flags', function (): void {
    $ad = $this->makeAd($this->seller, [
        'status' => AdStatus::PENDING->value,
        'moderation_result' => ModerationResult::rejected(
            ['phone', 'duplicate_image'],
            ['phone' => true, 'duplicate_image' => ['duplicate_ad_ids' => ['01HZZZZZZZZZZZZZZZZZZZZZZZ']]],
        ),
    ]);

    DetectDuplicateImagesJob::dispatchSync($ad->id);

    $result = $ad->fresh()?->moderation_result;
    expect($result?->flags)->toBe(['phone'])
        ->and($result?->details)->toBe(['phone' => true])
        ->and($result?->clean)->toBeFalse();
});

it('marks the result clean when the only finding was a stale duplicate', function (): void {
    $ad = $this->makeAd($this->seller, [
        'status' => AdStatus::PENDING->value,
        'moderation_result' => ModerationResult::clean()->withDuplicateImages(['01HZZZZZZZZZZZZZZZZZZZZZZZ']),
    ]);

    DetectDuplicateImagesJob::dispatchSync($ad->id);

    expect($ad->fresh()?->moderation_result?->clean)->toBeTrue();
});

it('skips ads that are no longer waiting for review', function (): void {
    $ad = $this->makeAd($this->seller, [
        'status' => AdStatus::ACTIVE->value,
        'published_at' => now()->subDay(),
        'expires_at' => now()->addDays(29),
    ]);

    DetectDuplicateImagesJob::dispatchSync($ad->id);

    expect($ad->fresh()?->moderation_result)->toBeNull();
});

it('queues the check again once the images of a pending ad are hashed', function (string $status, bool $expected): void {
    Queue::fake([DetectDuplicateImagesJob::class]);

    $ad = $this->makeAd($this->seller, ['status' => $status]);
    $media = $ad->addMedia(UploadedFile::fake()->image('photo.png', 32, 32))->toMediaCollection('images');

    ProcessAdImagesJob::dispatchSync([(string) $media->getKey()]);

    $expected
        ? Queue::assertPushed(DetectDuplicateImagesJob::class, fn (DetectDuplicateImagesJob $job): bool => $job->adId === $ad->id)
        : Queue::assertNotPushed(DetectDuplicateImagesJob::class);
})->with([
    'pending' => [AdStatus::PENDING->value, true],
    'draft' => [AdStatus::DRAFT->value, false],
]);
