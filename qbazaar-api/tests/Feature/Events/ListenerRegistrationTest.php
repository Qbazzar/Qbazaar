<?php

declare(strict_types=1);

use App\Events\Ads\AdApproved;
use App\Events\Ads\AdExpired;
use App\Events\Ads\AdExpiringSoon;
use App\Events\Ads\AdPriceDropped;
use App\Events\Ads\AdPublished;
use App\Events\Ads\AdRejected;
use App\Events\Ads\AdRenewed;
use App\Events\Ads\AdSubmittedForReview;
use App\Events\Messaging\MessageSent;
use App\Events\Offers\OfferAccepted;
use App\Events\Offers\OfferCountered;
use App\Events\Offers\OfferCreated;
use App\Events\Offers\OfferExpired;
use App\Events\Offers\OfferRejected;
use App\Events\Offers\OfferWithdrawn;
use App\Events\Reports\ReportCreated;
use App\Listeners\Ads\NotifyAdminsOfPendingAd;
use App\Listeners\Ads\NotifyFavoritersOfPriceDrop;
use App\Listeners\Ads\NotifyFollowersOfNewAd;
use App\Listeners\Ads\QueueDuplicateImageCheck;
use App\Listeners\Ads\SendAdNotifications;
use App\Listeners\Messaging\ScreenChatMessage;
use App\Listeners\Messaging\SendChatPushNotifications;
use App\Listeners\Notifications\BroadcastDatabaseNotificationCreated;
use App\Listeners\Notifications\PruneStaleDeviceTokens;
use App\Listeners\Reports\NotifyModeratorsOfReport;
use App\Listeners\Search\NotifySavedSearchMatches;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;

/**
 * @return list<string> application listeners as "Class@method", duplicates kept
 */
function applicationListenersFor(string $event): array
{
    $listeners = [];

    foreach (Event::getRawListeners()[$event] ?? [] as $listener) {
        $name = is_array($listener) ? implode('@', $listener) : $listener;

        if (is_string($name) && str_starts_with($name, 'App\\')) {
            $listeners[] = $name;
        }
    }

    return $listeners;
}

it('registers every application listener exactly once per event', function (): void {
    foreach (array_keys(Event::getRawListeners()) as $event) {
        $listeners = applicationListenersFor($event);

        expect($listeners)->toBe(array_values(array_unique($listeners)), "Duplicate listener for {$event}");
    }
});

it('wires each event to its listeners', function (string $event, array $listeners): void {
    $expected = array_map(fn (string $listener): string => "{$listener}@handle", $listeners);

    expect(applicationListenersFor($event))->toEqualCanonicalizing($expected);
})->with([
    'AdPublished' => [AdPublished::class, [SendAdNotifications::class, NotifySavedSearchMatches::class, NotifyFollowersOfNewAd::class]],
    'AdApproved' => [AdApproved::class, [SendAdNotifications::class, NotifySavedSearchMatches::class, NotifyFollowersOfNewAd::class]],
    'AdPriceDropped' => [AdPriceDropped::class, [NotifyFavoritersOfPriceDrop::class]],
    'AdRejected' => [AdRejected::class, [SendAdNotifications::class]],
    'AdExpired' => [AdExpired::class, [SendAdNotifications::class]],
    'AdExpiringSoon' => [AdExpiringSoon::class, [SendAdNotifications::class]],
    'AdRenewed' => [AdRenewed::class, [SendAdNotifications::class]],
    'AdSubmittedForReview' => [AdSubmittedForReview::class, [NotifyAdminsOfPendingAd::class, QueueDuplicateImageCheck::class]],
    'NotificationSent' => [NotificationSent::class, [BroadcastDatabaseNotificationCreated::class]],
    'NotificationFailed' => [NotificationFailed::class, [PruneStaleDeviceTokens::class]],
    'MessageSent' => [MessageSent::class, [SendChatPushNotifications::class, ScreenChatMessage::class]],
    'OfferCreated' => [OfferCreated::class, [SendChatPushNotifications::class]],
    'OfferCountered' => [OfferCountered::class, [SendChatPushNotifications::class]],
    'OfferAccepted' => [OfferAccepted::class, [SendChatPushNotifications::class]],
    'OfferRejected' => [OfferRejected::class, [SendChatPushNotifications::class]],
    'OfferWithdrawn' => [OfferWithdrawn::class, [SendChatPushNotifications::class]],
    'OfferExpired' => [OfferExpired::class, [SendChatPushNotifications::class]],
    'ReportCreated' => [ReportCreated::class, [NotifyModeratorsOfReport::class]],
]);
