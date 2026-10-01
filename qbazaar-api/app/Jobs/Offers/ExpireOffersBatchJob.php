<?php

declare(strict_types=1);

namespace App\Jobs\Offers;

use App\Enums\OfferStatus;
use App\Enums\QueueName;
use App\Models\Offer;
use App\Services\Offers\OfferTransitionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Expires one batch of offers queued by {@see ExpireOldOffersJob}. Each offer
 * goes through the locked transition, which re-checks status and expiry, so
 * a retry or a duplicate batch is harmless.
 */
class ExpireOffersBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public int $timeout = 60;

    /**
     * @param list<string> $offerIds
     */
    public function __construct(public readonly array $offerIds)
    {
        $this->onQueue(QueueName::LOW);
    }

    public function handle(OfferTransitionService $transitions): void
    {
        $offers = Offer::query()
            ->whereKey($this->offerIds)
            ->where('status', OfferStatus::PENDING->value)
            ->get();

        foreach ($offers as $offer) {
            $transitions->expireIfDue($offer);
        }
    }
}
