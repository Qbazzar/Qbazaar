<?php

declare(strict_types=1);

use App\Data\Account\NotificationPreferences;
use App\Enums\AdStatus;
use App\Events\Ads\AdApproved;
use App\Events\Ads\AdPublished;
use App\Jobs\Search\SendSavedSearchDigestJob;
use App\Listeners\Search\NotifySavedSearchMatches;
use App\Models\Ad;
use App\Models\User;
use App\Notifications\Search\SavedSearchDigestNotification;
use App\Notifications\Search\SavedSearchMatchNotification;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use NotificationChannels\Fcm\FcmChannel;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    config()->set('firebase.projects.' . config('firebase.default', 'app') . '.credentials', '{"type":"service_account"}');
    config()->set('qbazaar.search.saved_search_check_interval_minutes', 60);

    $this->owner = User::factory()->create();
    $this->owner->deviceTokens()->create(['token' => str_repeat('a', 40), 'platform' => 'web']);
    $this->ad = $this->makeAd(User::factory()->create(), ['status' => AdStatus::ACTIVE->value, 'published_at' => now()]);
});

it('queues one saved-search fan-out per ad when it is published and approved together', function (): void {
    Queue::fake();

    AdPublished::dispatch($this->ad);
    AdApproved::dispatch($this->ad);

    $fanOuts = Queue::pushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === NotifySavedSearchMatches::class);

    expect($fanOuts)->toHaveCount(1)
        ->and($fanOuts->first()->uniqueId)->toBe($this->ad->id);
});

it('pushes the first match at once and batches the rest of the window into one digest', function (): void {
    Queue::fake();

    $first = new SavedSearchMatchNotification($this->ad, 'Cars');
    $second = new SavedSearchMatchNotification(new Ad, 'Cars');
    $third = new SavedSearchMatchNotification(new Ad, 'Bikes');

    expect($first->via($this->owner))->toBe(['database', FcmChannel::class])
        ->and($second->via($this->owner))->toBe(['database'])
        ->and($third->via($this->owner))->toBe(['database']);

    Queue::assertPushed(SendSavedSearchDigestJob::class, 1);
    Queue::assertPushed(SendSavedSearchDigestJob::class, fn (SendSavedSearchDigestJob $job): bool => $job->userId === $this->owner->id
        && $job->delay !== null);
});

it('sends the digest with the batched count and starts a new window', function (): void {
    Queue::fake();
    Notification::fake();

    (new SavedSearchMatchNotification($this->ad, 'Cars'))->via($this->owner);
    (new SavedSearchMatchNotification(new Ad, 'Cars'))->via($this->owner);
    (new SavedSearchMatchNotification(new Ad, 'Cars'))->via($this->owner);

    app()->call([new SendSavedSearchDigestJob($this->owner->id), 'handle']);

    Notification::assertSentTo($this->owner, SavedSearchDigestNotification::class, fn (SavedSearchDigestNotification $digest): bool => $digest->matches === 2);
    expect((new SavedSearchMatchNotification(new Ad, 'Cars'))->via($this->owner))->toBe(['database']);
});

it('sends no digest when nothing was batched', function (): void {
    Notification::fake();

    app()->call([new SendSavedSearchDigestJob($this->owner->id), 'handle']);

    Notification::assertNothingSent();
});

it('keeps the digest push behind the watched-ad alerts switch', function (): void {
    $digest = new SavedSearchDigestNotification(3);

    expect($digest->via($this->owner))->toBe([FcmChannel::class])
        ->and($digest->toArray($this->owner)['category'])->toBe('search.digest');

    $this->owner->forceFill([
        'notification_preferences' => NotificationPreferences::defaults()->with(push: ['saved_search_alerts' => false]),
    ])->save();

    expect($digest->via($this->owner->fresh()))->toBe([]);
});
