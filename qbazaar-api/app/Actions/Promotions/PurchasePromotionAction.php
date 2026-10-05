<?php

declare(strict_types=1);

namespace App\Actions\Promotions;

use App\Enums\PromotionPaymentMethod;
use App\Enums\PromotionType;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\AdPromotion;
use App\Models\User;
use App\Services\Promotions\PromotionLifecycleService;

/**
 * The ad's owner buys a promotion for it. Anyone else gets AD_001, so a
 * stranger cannot tell someone else's ad from a missing one.
 */
class PurchasePromotionAction
{
    public function __construct(
        private readonly PromotionLifecycleService $promotions,
    ) {}

    public function __invoke(User $owner, string $adId, PromotionType $type, PromotionPaymentMethod $method, ?string $transferReference): AdPromotion
    {
        $ad = Ad::query()->find($adId);

        if ($ad === null || $ad->user_id !== $owner->id) {
            throw new DomainException(ErrorCode::AD_NOT_FOUND);
        }

        return $this->promotions->purchase($owner, $ad, $type, $method, $transferReference);
    }
}
