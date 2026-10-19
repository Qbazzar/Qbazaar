<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\Language;
use App\Enums\NotificationTopic;
use App\Models\User;
use App\Notifications\Concerns\DeliversOnNotificationsQueue;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use App\Notifications\Concerns\SendsFcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;

/**
 * Admin-broadcast announcement.
 *
 * Fan-out happens from the admin broadcast composer (AD-17.15); this class is
 * the unit of work that lands in each recipient's inbox.
 *
 *  - title + body are copy entered by staff and validated by the composer's
 *    form request (required, max length, no markup), so the notification
 *    stays a thin wrapper.
 *  - `$translations` holds the copy per language; each recipient gets the
 *    copy for their own language, falling back to `$title` / `$body`.
 *  - The database payload exposes `title` / `body` verbatim under stable
 *    keys so the FE bell icon can render without any locale lookup; the FCM
 *    push is built from the same payload.
 *  - Mail subject reuses the title; the body is wrapped in a plain
 *    MailMessage so an existing notification template applies. Broadcasts
 *    turn mail off unless staff ask for it.
 */
class SystemAnnouncementNotification extends Notification implements ShouldQueue
{
    use DeliversOnNotificationsQueue, Queueable, RespectsNotificationPreferences, SendsFcmPush;

    /**
     * @param array<string, array{title: string, body: string}> $translations language => copy
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $translations = [],
        public readonly bool $withMail = true,
        public readonly ?string $broadcastId = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        // Non-User notifiables (admin testing) would crash the database
        // channel — fall back to mail-only as the rest of the codebase does.
        $channels = $notifiable instanceof User
            ? ['mail', 'database']
            : ['mail'];

        if (! $this->withMail && $notifiable instanceof User) {
            $channels = ['database'];
        }

        if ($this->fcmEnabledFor($notifiable)) {
            $channels[] = FcmChannel::class;
        }

        return $this->withoutMutedChannels($notifiable, $channels);
    }

    protected function topic(): NotificationTopic
    {
        return NotificationTopic::NEWSLETTERS;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $locale = $this->resolveLocale($notifiable);
        $copy = $this->copyFor($locale);

        return (new MailMessage)
            ->subject($copy['title'])
            ->greeting(__('messages.notifications.system_announcement.greeting', [], $locale))
            ->line($copy['body'])
            ->line(__('messages.notifications.system_announcement.footer', [], $locale));
    }

    /**
     * Database-channel payload — stable keys consumed by the FE bell icon.
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $copy = $this->copyFor($this->resolveLocale($notifiable));

        return array_filter([
            'category' => 'system.announcement',
            'title' => $copy['title'],
            'body' => $copy['body'],
            'cta_url' => rtrim((string) config('qbazaar.web_url', config('app.url')), '/'),
            'icon' => 'megaphone',
            'broadcast_id' => $this->broadcastId,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array{title: string, body: string}
     */
    private function copyFor(string $locale): array
    {
        return $this->translations[$locale] ?? ['title' => $this->title, 'body' => $this->body];
    }

    private function resolveLocale(mixed $notifiable): string
    {
        if ($notifiable instanceof User) {
            return $notifiable->language instanceof Language
                ? $notifiable->language->value
                : (string) config('qbazaar.default_language', 'ar');
        }

        return (string) config('qbazaar.default_language', 'ar');
    }
}
