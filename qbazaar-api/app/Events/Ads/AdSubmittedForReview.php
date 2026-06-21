<?php

declare(strict_types=1);

namespace App\Events\Ads;

use App\Data\Moderation\ModerationResult;
use App\Models\Ad;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a seller publishes a draft. Every ad now enters PENDING and waits
 * for an admin to approve it before it goes live — so this event drives the
 * admin-facing "new ad to review" notification (panel bell).
 *
 * The {@see ModerationResult} rides along so the notification can hint which
 * (if any) auto-moderation rules fired, helping reviewers triage.
 */
class AdSubmittedForReview
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Ad $ad,
        public readonly ModerationResult $result,
    ) {}
}
