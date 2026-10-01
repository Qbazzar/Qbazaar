<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Enums\QueueName;
use App\Notifications\Channels\TwilioSmsChannel;
use NotificationChannels\Fcm\FcmChannel;

/**
 * Sends every channel of a queued notification from the notifications
 * queue. Laravel copies these settings onto each per-channel job, so an
 * SMTP, FCM or SMS outage of a few minutes is retried instead of lost.
 */
trait DeliversOnNotificationsQueue
{
    public int $tries = 5;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return array_fill_keys(
            ['mail', 'database', 'broadcast', FcmChannel::class, TwilioSmsChannel::class],
            QueueName::NOTIFICATIONS->value,
        );
    }
}
