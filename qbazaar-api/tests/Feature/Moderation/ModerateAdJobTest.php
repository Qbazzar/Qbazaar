<?php

declare(strict_types=1);

use App\Data\Moderation\ModerationResult;
use App\Enums\AdStatus;
use App\Events\Ads\AdModerated;
use App\Jobs\Ads\ModerateAdJob;
use App\Models\User;
use App\Services\Ads\AdLifecycleService;
use App\Services\Moderation\DuplicateImageDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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

it('publishes without running any moderation check in the request', function (): void {
    Queue::fake([ModerateAdJob::class]);
    Sanctum::actingAs($this->seller, ['*']);

    $draft = $this->makePublishableDraft($this->seller, [
        'description' => 'Comfortable reading chair in excellent condition. Call 55123456 for details.',
    ]);

    $this->mock(DuplicateImageDetector::class)->shouldNotReceive('findDuplicateAdIds');

    postJson("/api/v1/ads/{$draft->id}/publish", ['accepted_terms' => true])->assertOk();

    Queue::assertPushedOn('media', ModerateAdJob::class, fn (ModerateAdJob $job): bool => $job->adId === $draft->id);
    expect($draft->fresh()?->moderation_result)->toBeNull();
});

it('stores the text and image hints on the pending ad and alerts reviewers once', function (): void {
    Event::fake([AdModerated::class]);

    $ad = $this->makeAd($this->seller, [
        'status' => AdStatus::PENDING->value,
        'submitted_at' => now(),
        'description' => 'Comfortable reading chair in excellent condition. Call 55123456 for details.',
    ]);

    ModerateAdJob::dispatchSync($ad->id);
    ModerateAdJob::dispatchSync($ad->id);

    expect($ad->fresh()?->moderation_result?->flags)->toBe(['phone']);
    Event::assertDispatchedTimes(AdModerated::class, 1);
    Event::assertDispatched(AdModerated::class, fn (AdModerated $event): bool => $event->result->flags === ['phone']);
});

it('alerts reviewers again for a new submission of the same ad', function (): void {
    Event::fake([AdModerated::class]);
    $ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value, 'published_at' => now()->subDay()]);
    $lifecycle = app(AdLifecycleService::class);

    $lifecycle->submitForReview($ad);
    ModerateAdJob::dispatchSync($ad->id);
    $lifecycle->approve($ad);

    $this->travel(1)->minutes();
    $lifecycle->submitForReview($ad);
    ModerateAdJob::dispatchSync($ad->id);

    Event::assertDispatchedTimes(AdModerated::class, 2);
});

it('replaces a stale duplicate finding and keeps the text flags', function (): void {
    $ad = $this->makeAd($this->seller, [
        'status' => AdStatus::PENDING->value,
        'description' => 'Comfortable reading chair in excellent condition. Call 55123456 for details.',
        'moderation_result' => ModerationResult::rejected(
            ['phone', 'duplicate_image'],
            ['phone' => true, 'duplicate_image' => ['duplicate_ad_ids' => ['01HZZZZZZZZZZZZZZZZZZZZZZZ']]],
        ),
    ]);

    ModerateAdJob::dispatchSync($ad->id);

    $result = $ad->fresh()?->moderation_result;
    expect($result?->flags)->toBe(['phone'])
        ->and($result?->details)->toBe(['phone' => true])
        ->and($result?->clean)->toBeFalse();
});

it('skips ads that are no longer waiting for review', function (): void {
    $ad = $this->makeAd($this->seller, [
        'status' => AdStatus::ACTIVE->value,
        'published_at' => now()->subDay(),
        'expires_at' => now()->addDays(29),
    ]);

    ModerateAdJob::dispatchSync($ad->id);

    expect($ad->fresh()?->moderation_result)->toBeNull();
});

it('runs every check before the transaction that writes the result', function (): void {
    $ad = $this->makeAd($this->seller, ['status' => AdStatus::PENDING->value, 'submitted_at' => now()]);

    $levelsDuringScan = [];
    DB::listen(function ($query) use (&$levelsDuringScan): void {
        if (str_contains($query->sql, '"media"')) {
            $levelsDuringScan[] = DB::transactionLevel();
        }
    });

    ModerateAdJob::dispatchSync($ad->id);

    // RefreshDatabase wraps each test in one transaction, so 1 means "outside the job's own".
    expect($levelsDuringScan)->not->toBeEmpty()
        ->and(array_unique($levelsDuringScan))->toBe([1])
        ->and($ad->fresh()?->moderation_result?->clean)->toBeTrue();
});

it('is unique per ad until it starts', function (): void {
    expect((new ModerateAdJob('01HZZZZZZZZZZZZZZZZZZZZZZZ'))->uniqueId())->toBe('01HZZZZZZZZZZZZZZZZZZZZZZZ')
        ->and((new ModerateAdJob('x'))->tries)->toBe(3)
        ->and((new ModerateAdJob('x'))->timeout)->toBe(60);
});

it('still alerts reviewers, without hints, when every attempt failed', function (): void {
    Event::fake([AdModerated::class]);
    $ad = $this->makeAd($this->seller, ['status' => AdStatus::PENDING->value, 'submitted_at' => now()]);

    $this->mock(DuplicateImageDetector::class)
        ->shouldReceive('findDuplicateAdIds')
        ->andThrow(new RuntimeException('database gone'));

    expect(fn () => ModerateAdJob::dispatchSync($ad->id))->toThrow(RuntimeException::class);

    Event::assertDispatchedTimes(AdModerated::class, 1);
    Event::assertDispatched(AdModerated::class, fn (AdModerated $event): bool => $event->result->clean);
    expect($ad->fresh()?->moderation_result)->toBeNull();
});

it('does not alert twice when a failed re-run follows a stored result', function (): void {
    Event::fake([AdModerated::class]);
    $ad = $this->makeAd($this->seller, ['status' => AdStatus::PENDING->value, 'submitted_at' => now()]);
    ModerateAdJob::dispatchSync($ad->id);

    $this->mock(DuplicateImageDetector::class)
        ->shouldReceive('findDuplicateAdIds')
        ->andThrow(new RuntimeException('database gone'));

    expect(fn () => ModerateAdJob::dispatchSync($ad->id))->toThrow(RuntimeException::class);

    Event::assertDispatchedTimes(AdModerated::class, 1);
});
