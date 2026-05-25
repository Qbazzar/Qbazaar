<?php

declare(strict_types=1);

namespace App\Notifications\Ads;

use App\Enums\Language;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your ad is live" — sent when a draft clears auto-moderation (or admin
 * approval) and transitions to ACTIVE.
 *
 * Delivers via mail + database so the in-app bell icon picks it up too.
 * The CTA points at the public ad URL on the web app; the deep-link host
 * is configured via `qbazaar.web_url` so staging / prod stay separate.
 */
class AdApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Ad $ad) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        // Sprint 10 wired the database channel — every user gets the bell-icon
        // entry alongside the mail. Non-User notifiables (admin moderation,
        // future broadcasts) fall back to mail-only to avoid persisting rows
        // against the wrong morph type.
        return $notifiable instanceof User
            ? ['mail', 'database']
            : ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $locale = $this->resolveLocale($notifiable);

        return (new MailMessage)
            ->subject(__('messages.ad_notifications.approved.subject', [], $locale))
            ->greeting(__('messages.ad_notifications.approved.greeting', [], $locale))
            ->line(__('messages.ad_notifications.approved.line_intro', ['title' => $this->ad->title], $locale))
            ->action(__('messages.ad_notifications.approved.action', [], $locale), $this->adUrl())
            ->line(__('messages.ad_notifications.approved.line_outro', [], $locale));
    }

    /**
     * Database-channel payload — surfaced verbatim by `NotificationResource`.
     *
     * Keys are stable: rename a key here and you break every notification
     * already stored in the table. Add new keys instead of repurposing
     * existing ones.
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $locale = $this->resolveLocale($notifiable);

        return [
            'category' => 'ad.approved',
            'title' => __('messages.notifications.ad_approved.title', [], $locale),
            'body' => __('messages.notifications.ad_approved.body', ['title' => $this->ad->title], $locale),
            'cta_url' => $this->adUrl(),
            'icon' => 'badge-check',
            'ad_id' => $this->ad->id,
        ];
    }

    private function adUrl(): string
    {
        return rtrim((string) config('qbazaar.web_url', config('app.url')), '/') . '/ads/' . $this->ad->id;
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
