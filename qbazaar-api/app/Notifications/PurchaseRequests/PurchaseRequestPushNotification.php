<?php

declare(strict_types=1);

namespace App\Notifications\PurchaseRequests;

use App\Enums\NotificationTopic;
use App\Models\PurchaseRequest;
use App\Notifications\Messaging\ChatPushNotification;

/**
 * Push about a purchase request changing state, categorised by the broadcast
 * name of the event (purchase_request.created, purchase_request.accepted, ...).
 * Buy requests are deals like offers, so they follow the offers preference.
 */
class PurchaseRequestPushNotification extends ChatPushNotification
{
    public function __construct(
        public readonly PurchaseRequest $purchaseRequest,
        public readonly string $category,
    ) {}

    protected function topic(): NotificationTopic
    {
        return NotificationTopic::OFFERS;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $locale = $this->resolveLocale($notifiable);
        $translationKey = 'messages.notifications.' . str_replace('.', '_', $this->category);

        $this->purchaseRequest->loadMissing(['ad' => fn ($query) => $query->select(['id', 'title'])]);

        return [
            'category' => $this->category,
            'title' => (string) __($translationKey . '.title', [], $locale),
            'body' => (string) __($translationKey . '.body', [
                'amount' => $this->purchaseRequest->total(),
                'currency' => $this->purchaseRequest->currency,
                'quantity' => (string) $this->purchaseRequest->quantity,
                'title' => (string) $this->purchaseRequest->ad?->title,
            ], $locale),
            'cta_url' => $this->conversationUrl($this->purchaseRequest->conversation_id),
            'conversation_id' => $this->purchaseRequest->conversation_id,
            'purchase_request_id' => $this->purchaseRequest->id,
        ];
    }
}
