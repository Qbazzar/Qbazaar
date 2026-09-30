<?php

declare(strict_types=1);

namespace App\Events\Ads;

use App\Data\Moderation\ModerationResult;
use App\Models\Ad;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an ad enters PENDING: a seller publishes a draft, or edits the
 * content of a live ad (text, category or images). The ad waits for an admin
 * to approve it before it goes live — so this event drives the admin-facing
 * "new ad to review" notification (panel bell).
 *
 * The {@see ModerationResult} rides along so the notification can hint which
 * (if any) auto-moderation rules fired, helping reviewers triage.
 */
class AdSubmittedForReview implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Ad $ad,
        public readonly ModerationResult $result,
    ) {}
}
