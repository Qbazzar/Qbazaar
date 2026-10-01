<?php

declare(strict_types=1);

namespace App\Events\Ads;

use App\Jobs\Ads\ModerateAdJob;
use App\Models\Ad;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an ad enters PENDING: a seller publishes a draft, or edits the
 * content of a live ad (text, category or images). It queues the
 * auto-moderation ({@see ModerateAdJob}), which alerts the reviewers through
 * {@see AdModerated} once the triage hints are ready.
 */
class AdSubmittedForReview implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Ad $ad,
    ) {}
}
