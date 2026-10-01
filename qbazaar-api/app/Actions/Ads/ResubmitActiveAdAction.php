<?php

declare(strict_types=1);

namespace App\Actions\Ads;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Services\Ads\AdLifecycleService;
use Illuminate\Support\Facades\DB;

/**
 * Sends a live ad back to review after its images change. Ads that are not
 * ACTIVE are left alone: drafts and rejected ads are reviewed on publish,
 * and a pending ad is already in the queue.
 */
class ResubmitActiveAdAction
{
    public function __construct(
        private readonly AdLifecycleService $lifecycle,
    ) {}

    public function __invoke(Ad $ad): void
    {
        DB::transaction(function () use ($ad): void {
            /** @var Ad|null $locked */
            $locked = Ad::query()->lockForUpdate()->find($ad->getKey());

            if ($locked?->status !== AdStatus::ACTIVE) {
                return;
            }

            $this->lifecycle->submitForReview($locked);
        });
    }
}
