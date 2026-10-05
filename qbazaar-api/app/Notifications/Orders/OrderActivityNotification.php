<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Enums\NotificationTopic;
use App\Enums\OrderNotice;
use App\Models\Order;
use App\Notifications\ActivityNotification;

/**
 * Tells a buyer or a seller what happened to their order. Orders grow out
 * of offers and purchase requests, so they share the offers topic.
 */
class OrderActivityNotification extends ActivityNotification
{
    public function __construct(
        public readonly OrderNotice $notice,
        public readonly Order $order,
    ) {}

    protected function topic(): NotificationTopic
    {
        return NotificationTopic::OFFERS;
    }

    protected function category(): string
    {
        return $this->notice->category();
    }

    protected function translationKey(): string
    {
        return 'messages.order_notifications.' . $this->notice->value;
    }

    /**
     * @return array<string, string|int>
     */
    protected function translationParams(string $locale): array
    {
        return [
            'title' => $this->order->ad_title,
            'total' => $this->order->total,
            'commission' => $this->order->commission_amount,
            'currency' => $this->order->currency,
        ];
    }

    protected function ctaPath(): string
    {
        return '/account/orders/' . $this->order->id;
    }

    protected function icon(): string
    {
        return $this->notice->icon();
    }

    /**
     * @return array<string, string|null>
     */
    protected function references(): array
    {
        return ['order_id' => $this->order->id];
    }
}
