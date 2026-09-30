<?php

declare(strict_types=1);

namespace App\Notifications\Support;

use App\Enums\Language;
use App\Models\SupportReply;
use App\Models\User;
use App\Notifications\Concerns\SendsFcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;

/**
 * Tells the ticket owner that staff replied.
 *
 * Guest tickets are routed to their contact email only, so the mail carries
 * the reply text itself: a guest has no account page to read it on.
 */
class SupportTicketRepliedNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsFcmPush;

    public function __construct(public readonly SupportReply $reply) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        $channels = ['mail', 'database'];

        if ($this->fcmEnabledFor($notifiable)) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $locale = $this->resolveLocale($notifiable);
        $subject = $this->reply->ticket->subject;

        $mail = (new MailMessage)
            ->subject(__('messages.support_notifications.reply.subject', [], $locale))
            ->greeting(__('messages.support_notifications.reply.greeting', [], $locale))
            ->line(__('messages.support_notifications.reply.line_intro', ['subject' => $subject], $locale))
            ->line($this->reply->body);

        if ($notifiable instanceof User) {
            $mail->action(__('messages.support_notifications.reply.action', [], $locale), $this->ticketUrl());
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $locale = $this->resolveLocale($notifiable);

        return [
            'category' => 'support.reply',
            'title' => __('messages.notifications.support_reply.title', [], $locale),
            'body' => __('messages.notifications.support_reply.body', ['subject' => $this->reply->ticket->subject], $locale),
            'cta_url' => $this->ticketUrl(),
            'icon' => 'life-buoy',
            'ticket_id' => $this->reply->ticket_id,
        ];
    }

    private function ticketUrl(): string
    {
        return rtrim((string) config('qbazaar.web_url', config('app.url')), '/') . '/account/support/' . $this->reply->ticket_id;
    }

    private function resolveLocale(mixed $notifiable): string
    {
        return $notifiable instanceof User && $notifiable->language instanceof Language
            ? $notifiable->language->value
            : (string) config('qbazaar.default_language', 'ar');
    }
}
