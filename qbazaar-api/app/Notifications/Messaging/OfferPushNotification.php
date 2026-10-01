<?php

declare(strict_types=1);

namespace App\Notifications\Messaging;

use App\Enums\NotificationTopic;
use App\Models\Offer;

/**
 * Push about an offer changing state, categorised by the broadcast name of
 * the event that triggered it (offer.created, offer.accepted, ...).
 */
class OfferPushNotification extends ChatPushNotification
{
    public function __construct(
        public readonly Offer $offer,
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

        // The ad may already be deleted when its offers expire with it.
        $this->offer->loadMissing(['ad' => fn ($query) => $query->withTrashed()->select(['id', 'title'])]);

        return [
            'category' => $this->category,
            'title' => (string) __($translationKey . '.title', [], $locale),
            'body' => (string) __($translationKey . '.body', [
                'amount' => number_format((float) $this->offer->amount, 2, '.', ''),
                'currency' => (string) config('qbazaar.default_currency', 'QAR'),
                'title' => $this->offer->ad->title,
            ], $locale),
            'cta_url' => $this->conversationUrl($this->offer->conversation_id),
            'conversation_id' => $this->offer->conversation_id,
            'offer_id' => $this->offer->id,
        ];
    }
}
