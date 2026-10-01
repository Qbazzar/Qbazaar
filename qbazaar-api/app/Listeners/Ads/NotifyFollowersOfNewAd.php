<?php

declare(strict_types=1);

namespace App\Listeners\Ads;

use App\Enums\QueueName;
use App\Events\Ads\AdApproved;
use App\Events\Ads\AdPublished;
use App\Notifications\Ads\NewAdFromFollowedSellerNotification;
use App\Services\Notifications\AdAudienceNotifier;
use App\Services\Users\SellerFollowers;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Alerts the seller's followers when a listing goes live. A re-approval
 * after an edit does not alert them again (per-user claim on the ad).
 */
class NotifyFollowersOfNewAd implements ShouldQueue
{
    public string $queue = QueueName::NOTIFICATIONS->value;

    // Recipients are claimed once per event, so a retry only reaches the ones not alerted yet.
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public int $timeout = 120;

    public function __construct(
        private readonly SellerFollowers $followers,
        private readonly AdAudienceNotifier $notifier,
    ) {}

    public function handle(AdPublished|AdApproved $event): void
    {
        $ad = $event->ad->loadMissing('user');

        if (! $ad->isPubliclyListed()) {
            return;
        }

        $notification = new NewAdFromFollowedSellerNotification($ad);

        $this->followers->chunkFollowerIds(
            $ad->user_id,
            (int) config('qbazaar.notifications.fan_out_chunk'),
            /** @param list<string> $followerIds */
            function (array $followerIds) use ($ad, $notification): void {
                $this->notifier->notify($ad, $followerIds, $notification, "new-ad:{$ad->id}");
            },
        );
    }
}
