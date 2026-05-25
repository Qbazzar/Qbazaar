<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\Language;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Admin-broadcast announcement (Sprint 11).
 *
 * Fan-out happens from the admin "Send announcement" action; this class is
 * the unit of work that lands in each recipient's inbox.
 *
 *  - title + body are arbitrary copy entered by the admin. We trust the
 *    Filament form's validation upstream (required, max length) so the
 *    notification stays a thin wrapper.
 *  - The database payload exposes `title` / `body` verbatim under stable
 *    keys so the FE bell icon can render without any locale lookup — the
 *    admin already chose the language by virtue of writing the copy.
 *  - Mail subject reuses the title; the body is wrapped in a plain
 *    MailMessage so an existing notification template applies.
 */
class SystemAnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $body,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        // Non-User notifiables (admin testing) would crash the database
        // channel — fall back to mail-only as the rest of the codebase does.
        return $notifiable instanceof User
            ? ['mail', 'database']
            : ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $locale = $this->resolveLocale($notifiable);

        return (new MailMessage)
            ->subject($this->title)
            ->greeting(__('messages.notifications.system_announcement.greeting', [], $locale))
            ->line($this->body)
            ->line(__('messages.notifications.system_announcement.footer', [], $locale));
    }

    /**
     * Database-channel payload — stable keys consumed by the FE bell icon.
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'category' => 'system.announcement',
            'title' => $this->title,
            'body' => $this->body,
            'cta_url' => rtrim((string) config('qbazaar.web_url', config('app.url')), '/'),
            'icon' => 'megaphone',
        ];
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
