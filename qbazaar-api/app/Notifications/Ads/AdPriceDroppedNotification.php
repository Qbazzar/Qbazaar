<?php

declare(strict_types=1);

namespace App\Notifications\Ads;

use App\Models\Ad;

/**
 * Tells users who favourited an ad that its price went down.
 */
class AdPriceDroppedNotification extends AdAlertNotification
{
    public const CATEGORY = 'ad.price_changed';

    public function __construct(
        Ad $ad,
        public readonly string $previousPrice,
        public readonly string $newPrice,
    ) {
        parent::__construct($ad);
    }

    protected function category(): string
    {
        return self::CATEGORY;
    }

    protected function title(string $locale): string
    {
        return (string) __('messages.notifications.ad_price_changed.title', [], $locale);
    }

    protected function body(string $locale): string
    {
        return (string) __('messages.notifications.ad_price_changed.body', [
            'title' => $this->ad->title,
            'previous' => $this->previousPrice,
            'price' => $this->newPrice,
            'currency' => $this->ad->currency,
        ], $locale);
    }

    protected function details(): array
    {
        return [
            'previous_price' => $this->previousPrice,
            'price' => $this->newPrice,
            'currency' => $this->ad->currency,
        ];
    }
}
