<?php

declare(strict_types=1);

namespace App\Listeners\Ads;

use App\Enums\QueueName;
use App\Events\Ads\AdPriceDropped;
use App\Models\Favorite;
use App\Notifications\Ads\AdPriceDroppedNotification;
use App\Services\Notifications\AdAudienceNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;

/**
 * Alerts everyone who favourited the ad, a chunk of favourites at a time.
 */
class NotifyFavoritersOfPriceDrop implements ShouldQueue
{
    public string $queue = QueueName::NOTIFICATIONS->value;

    // Recipients are claimed once per event, so a retry only reaches the ones not alerted yet.
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public int $timeout = 120;

    public function __construct(private readonly AdAudienceNotifier $notifier) {}

    public function handle(AdPriceDropped $event): void
    {
        $ad = $event->ad->loadMissing('user');

        // A later edit may already have changed the price again; that edit
        // raises its own event if it was another drop.
        if (! $ad->isPubliclyListed() || (string) $ad->price !== $event->newPrice) {
            return;
        }

        $notification = new AdPriceDroppedNotification($ad, $event->previousPrice, $event->newPrice);
        $scope = "price-drop:{$ad->id}:{$event->newPrice}";

        Favorite::query()
            ->where('ad_id', $ad->id)
            ->select(['id', 'user_id'])
            ->chunkById((int) config('qbazaar.notifications.fan_out_chunk'), function (Collection $favorites) use ($ad, $notification, $scope): void {
                /** @var list<string> $userIds */
                $userIds = $favorites->pluck('user_id')->values()->all();

                $this->notifier->notify($ad, $userIds, $notification, $scope);
            });
    }
}
