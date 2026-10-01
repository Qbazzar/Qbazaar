<?php

declare(strict_types=1);

namespace App\Notifications\Messaging;

use App\Enums\Language;
use App\Models\User;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use App\Notifications\Concerns\SendsFcmPush;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;

/**
 * Push-only notification about chat activity.
 *
 * Chat already has its own unread badges, so these never land in the
 * notification bell: they exist only to reach a recipient whose app is
 * closed. Sent synchronously because the listener dispatching them is
 * already queued.
 */
abstract class ChatPushNotification extends Notification
{
    use RespectsNotificationPreferences, SendsFcmPush;

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return $this->withoutMutedChannels($notifiable, $this->fcmEnabledFor($notifiable) ? [FcmChannel::class] : []);
    }

    protected function conversationUrl(string $conversationId): string
    {
        return rtrim((string) config('qbazaar.web_url', config('app.url')), '/')
            . '/account/messages?c=' . $conversationId;
    }

    protected function resolveLocale(mixed $notifiable): string
    {
        return $notifiable instanceof User && $notifiable->language instanceof Language
            ? $notifiable->language->value
            : (string) config('qbazaar.default_language', 'ar');
    }
}
