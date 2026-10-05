<?php

declare(strict_types=1);

namespace App\Listeners\Promotions;

use App\Enums\PromotionNotice;
use App\Enums\PromotionPaymentMethod;
use App\Events\Promotions\PromotionActivated;
use App\Events\Promotions\PromotionExpired;
use App\Events\Promotions\PromotionRejected;
use App\Models\Ad;
use App\Models\User;
use App\Notifications\Promotions\PromotionActivityNotification;

/**
 * Tells the seller what happened to a promotion they bought. A wallet
 * purchase starts at the seller's own tap, so only a promotion an admin
 * activated (bank transfer) is announced.
 */
class SendPromotionNotifications
{
    public function handle(PromotionActivated|PromotionExpired|PromotionRejected $event): void
    {
        $promotion = $event->promotion;

        if ($event instanceof PromotionActivated && $promotion->payment_method === PromotionPaymentMethod::WALLET) {
            return;
        }

        $owner = $promotion->user_id === null ? null : User::query()->find($promotion->user_id);

        if ($owner === null) {
            return;
        }

        $notice = match (true) {
            $event instanceof PromotionActivated => PromotionNotice::ACTIVATED,
            $event instanceof PromotionExpired => PromotionNotice::EXPIRED,
            $event instanceof PromotionRejected => PromotionNotice::REJECTED,
        };
        $adTitle = (string) Ad::withTrashed()->whereKey($promotion->ad_id)->value('title');

        $owner->notify(new PromotionActivityNotification($notice, $promotion, $adTitle));
    }
}
