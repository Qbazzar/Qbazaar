<?php

declare(strict_types=1);

namespace App\Actions\Promotions;

use App\Models\AdPromotion;
use App\Models\User;
use App\Services\Promotions\PromotionLifecycleService;

/**
 * An admin's ruling on a promotion paid by bank transfer: confirming starts
 * it and books the revenue; rejecting closes it with nothing booked. Both
 * land in the activity log with the admin who made them.
 */
class ReviewPromotionTransferAction
{
    public function __construct(
        private readonly PromotionLifecycleService $promotions,
    ) {}

    public function confirm(AdPromotion $promotion, User $admin): AdPromotion
    {
        $promotion = $this->promotions->confirmTransfer($promotion, $admin);
        $this->log($admin, $promotion, 'confirmed');

        return $promotion;
    }

    public function reject(AdPromotion $promotion, User $admin, ?string $reason): AdPromotion
    {
        $promotion = $this->promotions->rejectTransfer($promotion, $admin, $reason);
        $this->log($admin, $promotion, 'rejected', ['reason' => $reason]);

        return $promotion;
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function log(User $admin, AdPromotion $promotion, string $event, array $properties = []): void
    {
        activity('finance')
            ->causedBy($admin)
            ->performedOn($promotion)
            ->event($event)
            ->withProperties(['price' => $promotion->price, 'transfer_reference' => $promotion->transfer_reference, ...$properties])
            ->log('Promotion transfer ' . $event);
    }
}
