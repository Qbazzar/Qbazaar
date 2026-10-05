<?php

declare(strict_types=1);

namespace App\Jobs\Promotions;

use App\Enums\PromotionStatus;
use App\Enums\QueueName;
use App\Models\AdPromotion;
use App\Services\Promotions\PromotionLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Expires one batch of promotions queued by {@see ExpirePromotionsJob}. Each
 * goes through the locked transition, which re-checks status and end time,
 * so a retry or a duplicate batch is harmless.
 */
class ExpirePromotionsBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public int $timeout = 60;

    /**
     * @param list<string> $promotionIds
     */
    public function __construct(public readonly array $promotionIds)
    {
        $this->onQueue(QueueName::LOW);
    }

    public function handle(PromotionLifecycleService $promotions): void
    {
        $due = AdPromotion::query()
            ->whereKey($this->promotionIds)
            ->where('status', PromotionStatus::ACTIVE->value)
            ->get();

        foreach ($due as $promotion) {
            $promotions->expireIfDue($promotion);
        }
    }
}
