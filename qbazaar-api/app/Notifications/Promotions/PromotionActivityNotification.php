<?php

declare(strict_types=1);

namespace App\Notifications\Promotions;

use App\Enums\NotificationTopic;
use App\Enums\PromotionNotice;
use App\Models\AdPromotion;
use App\Notifications\ActivityNotification;

/**
 * Tells a seller that a paid promotion of their ad started, ended, or had
 * its bank transfer rejected.
 */
class PromotionActivityNotification extends ActivityNotification
{
    /**
     * The ad title is captured when the notice is created: the ad may be
     * gone by the time the queued notification is delivered.
     */
    public function __construct(
        public readonly PromotionNotice $notice,
        public readonly AdPromotion $promotion,
        public readonly string $adTitle,
    ) {}

    protected function topic(): NotificationTopic
    {
        return NotificationTopic::LISTING_UPDATES;
    }

    protected function category(): string
    {
        return $this->notice->category();
    }

    protected function translationKey(): string
    {
        return 'messages.promotion_notifications.' . $this->notice->value;
    }

    /**
     * @return array<string, string|int>
     */
    protected function translationParams(string $locale): array
    {
        return [
            'title' => $this->adTitle,
            'type' => (string) __('messages.promotion_types.' . $this->promotion->type->value, [], $locale),
            'days' => $this->promotion->duration_days,
            'price' => $this->promotion->price,
            'currency' => $this->promotion->currency,
            'reason' => (string) $this->promotion->rejection_reason,
        ];
    }

    protected function ctaPath(): string
    {
        return '/account/promotions';
    }

    protected function icon(): string
    {
        return 'sparkles';
    }

    /**
     * @return array<string, string|null>
     */
    protected function references(): array
    {
        return [
            'promotion_id' => $this->promotion->id,
            'ad_id' => $this->promotion->ad_id,
        ];
    }
}
