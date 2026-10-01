<?php

declare(strict_types=1);

namespace App\Events\Ads;

use App\Data\Moderation\ModerationResult;
use App\Models\Ad;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once per review submission, when the queued auto-moderation has
 * stored its first result on the pending ad. Reviewers are alerted from
 * here so the alert can carry the triage hints.
 */
class AdModerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Ad $ad,
        public readonly ModerationResult $result,
    ) {}
}
