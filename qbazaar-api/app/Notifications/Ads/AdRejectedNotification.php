<?php

declare(strict_types=1);

namespace App\Notifications\Ads;

use App\Data\Moderation\ModerationResult;
use App\Enums\Language;
use App\Models\Ad;
use App\Models\User;
use App\Notifications\Concerns\SendsFcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;

/**
 * "We need to review your ad" — sent when auto-moderation flags a draft.
 *
 * Reasons are surfaced as a human-readable bullet list (one localised line per
 * flag) so the seller knows what to fix. The CTA deep-links to the Edit Ad
 * screen on the web app — sellers can resubmit after edits.
 */
class AdRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsFcmPush;

    public function __construct(
        public readonly Ad $ad,
        public readonly ModerationResult $result,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = $notifiable instanceof User
            ? ['mail', 'database']
            : ['mail'];

        if ($this->fcmEnabledFor($notifiable)) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $locale = $this->resolveLocale($notifiable);

        $reasons = $this->localisedReasons($locale);
        $reasonsLine = implode(' ', $reasons);

        return (new MailMessage)
            ->subject(__('messages.ad_notifications.rejected.subject', [], $locale))
            ->greeting(__('messages.ad_notifications.rejected.greeting', [], $locale))
            ->line(__('messages.ad_notifications.rejected.line_intro', ['title' => $this->ad->title], $locale))
            ->line(__('messages.ad_notifications.rejected.line_reasons', ['reasons' => $reasonsLine], $locale))
            ->action(__('messages.ad_notifications.rejected.action', [], $locale), $this->editUrl())
            ->line(__('messages.ad_notifications.rejected.line_outro', [], $locale));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $locale = $this->resolveLocale($notifiable);

        return [
            'category' => 'ad.rejected',
            'title' => __('messages.notifications.ad_rejected.title', [], $locale),
            'body' => __('messages.notifications.ad_rejected.body', ['title' => $this->ad->title], $locale),
            'cta_url' => $this->editUrl(),
            'icon' => 'shield-alert',
            'ad_id' => $this->ad->id,
            'flags' => $this->result->flags,
        ];
    }

    /**
     * Map flag keys to localised one-line reasons. Falls back to the raw key
     * when no translation exists — keeps the email useful even after we add a
     * new flag without updating lang files.
     *
     * @return list<string>
     */
    private function localisedReasons(string $locale): array
    {
        $lines = [];
        foreach ($this->result->flags as $flag) {
            $key = 'messages.ad_notifications.rejected.reasons.' . $flag;
            $translated = __($key, [], $locale);
            $lines[] = is_string($translated) && $translated !== $key ? $translated : $flag;
        }

        return $lines;
    }

    private function editUrl(): string
    {
        return rtrim((string) config('qbazaar.web_url', config('app.url')), '/') . '/account/ads/' . $this->ad->id . '/edit';
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
