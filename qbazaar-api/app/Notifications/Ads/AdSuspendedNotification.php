<?php

declare(strict_types=1);

namespace App\Notifications\Ads;

use App\Enums\SanctionAction;
use App\Enums\SanctionReason;
use App\Notifications\Concerns\DeliversOnNotificationsQueue;
use App\Notifications\Concerns\ResolvesRecipientLocale;
use App\Notifications\Concerns\SendsFcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;

/**
 * Tells a seller that staff suspended or removed their ad, and why (the
 * preset reason only; the staff note stays internal). A policy notice, so it
 * has no topic and ignores the listing-update mutes.
 *
 * Carries the ad's id and title rather than the model: a removed ad is soft
 * deleted and must still render on the queue.
 */
class AdSuspendedNotification extends Notification implements ShouldQueue
{
    use DeliversOnNotificationsQueue, Queueable, ResolvesRecipientLocale, SendsFcmPush;

    public function __construct(
        public readonly string $adId,
        public readonly string $adTitle,
        public readonly SanctionAction $action,
        public readonly SanctionReason $reason,
    ) {}

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = ['mail', 'database'];

        if ($this->fcmEnabledFor($notifiable)) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $locale = $this->recipientLocale($notifiable);
        $key = $this->copyKey();

        return (new MailMessage)
            ->subject(__("messages.moderation.{$key}.subject", [], $locale))
            ->greeting(__('messages.moderation.greeting', [], $locale))
            ->line(__("messages.moderation.{$key}.line_intro", ['title' => $this->adTitle], $locale))
            ->line(__('messages.moderation.line_reason', ['reason' => $this->reason->userLabel($locale)], $locale))
            ->action(__('messages.moderation.action_help', [], $locale), $this->helpUrl())
            ->line(__('messages.moderation.line_appeal', [], $locale));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $locale = $this->recipientLocale($notifiable);
        $key = $this->copyKey();

        return [
            'category' => $this->action === SanctionAction::AD_REMOVED ? 'ad.removed' : 'ad.suspended',
            'title' => __("messages.moderation.{$key}.title", [], $locale),
            'body' => __("messages.moderation.{$key}.body", [
                'title' => $this->adTitle,
                'reason' => $this->reason->userLabel($locale),
            ], $locale),
            'cta_url' => $this->helpUrl(),
            'icon' => 'shield-alert',
            'ad_id' => $this->adId,
            'reason' => $this->reason->value,
        ];
    }

    private function copyKey(): string
    {
        return $this->action === SanctionAction::AD_REMOVED ? 'ad_removed' : 'ad_suspended';
    }

    private function helpUrl(): string
    {
        return rtrim((string) config('qbazaar.web_url', config('app.url')), '/') . '/support';
    }
}
