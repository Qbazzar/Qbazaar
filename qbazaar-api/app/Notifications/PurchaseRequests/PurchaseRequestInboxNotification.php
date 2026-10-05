<?php

declare(strict_types=1);

namespace App\Notifications\PurchaseRequests;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Notifications\Concerns\DeliversOnNotificationsQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * The inbox row for a purchase request change, so the other side finds it in
 * the bell after the push is gone. Inbox only: no email, and the push is the
 * chat push's job. The text is the push text, in the recipient's language.
 */
class PurchaseRequestInboxNotification extends Notification implements ShouldQueue
{
    use DeliversOnNotificationsQueue, Queueable;

    public function __construct(
        public readonly PurchaseRequest $purchaseRequest,
        public readonly string $category,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return $notifiable instanceof User ? ['database'] : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            ...(new PurchaseRequestPushNotification($this->purchaseRequest, $this->category))->toArray($notifiable),
            'icon' => 'shopping-bag',
        ];
    }
}
