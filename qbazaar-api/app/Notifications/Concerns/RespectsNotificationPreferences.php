<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Enums\NotificationPreferenceChannel;
use App\Enums\NotificationTopic;
use App\Models\User;
use NotificationChannels\Fcm\FcmChannel;

/**
 * Drops the mail and push channels the recipient muted for this
 * notification's topic. The database (inbox) channel is never dropped.
 */
trait RespectsNotificationPreferences
{
    abstract protected function topic(): NotificationTopic;

    /**
     * @param list<string> $channels
     * @return list<string>
     */
    protected function withoutMutedChannels(mixed $notifiable, array $channels): array
    {
        if (! $notifiable instanceof User) {
            return $channels;
        }

        $preferences = $notifiable->notification_preferences;

        return array_values(array_filter(
            $channels,
            fn (string $channel): bool => match ($channel) {
                'mail' => $preferences->allows($this->topic(), NotificationPreferenceChannel::EMAIL),
                FcmChannel::class => $preferences->allows($this->topic(), NotificationPreferenceChannel::PUSH),
                default => true,
            },
        ));
    }
}
