<?php

declare(strict_types=1);

namespace App\Notifications\Support;

use App\Models\SupportTicket;
use App\Notifications\Concerns\DeliversOnNotificationsQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Surfaces a newly submitted ticket in the support staff's panel bell.
 * Database-only, like the ad review queue notification.
 */
class SupportTicketCreatedNotification extends Notification implements ShouldQueue
{
    use DeliversOnNotificationsQueue, Queueable;

    public function __construct(public readonly SupportTicket $ticket) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $locale = (string) config('app.admin_locale', 'ar');

        return [
            'category' => 'support.ticket_created',
            'title' => __('admin.support_ticket_created.title', [], $locale),
            'body' => __('admin.support_ticket_created.body', ['subject' => $this->ticket->subject], $locale),
            'cta_url' => route('admin.support.show', $this->ticket),
            'icon' => 'life-buoy',
            'ticket_id' => $this->ticket->id,
        ];
    }
}
