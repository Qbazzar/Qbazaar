<?php

declare(strict_types=1);

namespace App\Notifications\Ads;

/**
 * Tells a seller's followers about a listing that just went live.
 */
class NewAdFromFollowedSellerNotification extends AdAlertNotification
{
    public const CATEGORY = 'ads.new_from_followed';

    protected function category(): string
    {
        return self::CATEGORY;
    }

    protected function title(string $locale): string
    {
        return (string) __('messages.notifications.ad_new_from_followed.title', ['name' => $this->sellerName()], $locale);
    }

    protected function body(string $locale): string
    {
        return (string) __('messages.notifications.ad_new_from_followed.body', [
            'name' => $this->sellerName(),
            'title' => $this->ad->title,
        ], $locale);
    }

    protected function details(): array
    {
        return ['seller_id' => $this->ad->user_id];
    }

    private function sellerName(): string
    {
        return (string) $this->ad->loadMissing('user')->user->full_name;
    }
}
