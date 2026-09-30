<?php

declare(strict_types=1);

namespace App\Actions\Ads;

use App\Events\Ads\AdSubmittedForReview;
use App\Models\Ad;

/**
 * Parks an ad in PENDING and queues it for manual review.
 *
 * Auto-moderation only produces triage hints for the reviewer: the ad waits
 * for an admin decision whatever the result.
 */
class SubmitAdForReviewAction
{
    public function __construct(
        private readonly ModerateAdAction $moderate,
    ) {}

    public function __invoke(Ad $ad): void
    {
        $result = ($this->moderate)($ad);

        $ad->holdForReview();

        AdSubmittedForReview::dispatch($ad, $result);
    }
}
