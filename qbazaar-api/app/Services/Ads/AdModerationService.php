<?php

declare(strict_types=1);

namespace App\Services\Ads;

use App\Data\Moderation\ModerationResult;
use App\Enums\AdStatus;
use App\Events\Ads\AdApproved;
use App\Events\Ads\AdRejected;
use App\Models\Ad;

/**
 * Central home for admin-driven ad moderation transitions.
 *
 * Extracted so every surface — the Filament panel and the custom /manage
 * panel — performs the same lifecycle changes and fires the same downstream
 * events (notifications, search re-index). The methods are intentionally
 * side-effect-only (no UI concerns) so callers own their own flash/notify.
 */
class AdModerationService
{
    /**
     * Approve a pending ad: ACTIVE via the publish() lifecycle + AdApproved so
     * notification and search-index listeners run — identical to a public
     * approval on purpose, admin approvals must be indistinguishable downstream.
     */
    public function approve(Ad $ad): void
    {
        $ad->publish();
        AdApproved::dispatch($ad);
    }

    /**
     * Reject a pending ad, persisting the admin note wrapped in a
     * ModerationResult so AdRejected keeps its existing shape.
     */
    public function reject(Ad $ad, string $notes): void
    {
        $ad->forceFill([
            'status' => AdStatus::REJECTED,
            'published_at' => null,
            'expires_at' => null,
        ])->save();

        $ad->unsearchable();

        AdRejected::dispatch($ad, ModerationResult::rejected(['admin_manual'], ['admin_notes' => $notes]));
    }

    /** Suspend a live ad (ACTIVE → BLOCKED) and pull it from search. */
    public function suspend(Ad $ad): void
    {
        $ad->forceFill(['status' => AdStatus::BLOCKED])->save();
        $ad->unsearchable();
    }

    /** Lift a suspension (BLOCKED → ACTIVE) and re-index it. */
    public function unsuspend(Ad $ad): void
    {
        $ad->publish();
        AdApproved::dispatch($ad);
    }

    /** Toggle the featured flag. Returns the new state. */
    public function toggleFeature(Ad $ad): bool
    {
        $next = ! $ad->featured;
        $ad->forceFill(['featured' => $next])->save();

        return $next;
    }

    /** Force a live ad to expire immediately. */
    public function forceExpire(Ad $ad): void
    {
        $ad->markExpired();
    }
}
