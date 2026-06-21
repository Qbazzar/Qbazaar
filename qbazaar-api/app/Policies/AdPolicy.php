<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\User;

/**
 * Authorization rules for ads — ownership + status gating.
 *
 * Admin override comes later (Sprint 12 backoffice). Until then every rule
 * here reduces to "is the caller the seller?" plus a status whitelist for
 * mutations. The status checks are the policy's value-add: they refuse
 * destructive operations on terminal-state ads even when the caller owns
 * the row.
 *
 * Wider business rules with their own error codes (eg. "you've hit the
 * daily publish cap") belong in actions / services that throw DomainException
 * so the stable code surfaces in the envelope.
 */
class AdPolicy
{
    /**
     * Public view rule. Anonymous callers can see ACTIVE / SOLD ads;
     * everything else (drafts, pending moderation, expired) is private
     * to the owner.
     */
    public function view(?User $user, Ad $ad): bool
    {
        if (in_array($ad->status, [AdStatus::ACTIVE, AdStatus::SOLD], true)) {
            return true;
        }

        return $user !== null && $user->id === $ad->user_id;
    }

    /**
     * Edit rule. Owners can edit ads that are still in flight or live;
     * sold / expired / rejected / blocked ads are immutable.
     */
    public function update(User $user, Ad $ad): bool
    {
        if ($user->id !== $ad->user_id) {
            return false;
        }

        // REJECTED is editable too — the owner fixes the ad and re-submits it
        // (publishing then sends it back to PENDING for review).
        return in_array($ad->status, [
            AdStatus::DRAFT,
            AdStatus::ACTIVE,
            AdStatus::PENDING,
            AdStatus::REJECTED,
        ], true);
    }

    public function delete(User $user, Ad $ad): bool
    {
        return $user->id === $ad->user_id;
    }

    /**
     * Publish rule. Owners can publish a DRAFT or re-submit a PENDING /
     * REJECTED ad (Wave B auto-moderation feedback loop).
     */
    public function publish(User $user, Ad $ad): bool
    {
        return $user->id === $ad->user_id
            && in_array($ad->status, [
                AdStatus::DRAFT,
                AdStatus::PENDING,
                AdStatus::REJECTED,
            ], true);
    }

    /**
     * Image-management rule (upload / delete / reorder). Sellers can manage
     * images while the ad is still mutable — terminal-state ads are frozen.
     */
    public function manageImages(User $user, Ad $ad): bool
    {
        if ($user->id !== $ad->user_id) {
            return false;
        }

        return ! in_array($ad->status, [
            AdStatus::SOLD,
            AdStatus::BLOCKED,
            AdStatus::REJECTED,
        ], true);
    }

    /**
     * Mark-as-sold rule. Owner can flip ACTIVE / EXPIRED ads to SOLD.
     */
    public function markSold(User $user, Ad $ad): bool
    {
        return $user->id === $ad->user_id
            && in_array($ad->status, [AdStatus::ACTIVE, AdStatus::EXPIRED], true);
    }

    /**
     * Renew rule. Owner can extend ACTIVE / EXPIRED ads' expiry window.
     */
    public function renew(User $user, Ad $ad): bool
    {
        return $user->id === $ad->user_id
            && in_array($ad->status, [AdStatus::ACTIVE, AdStatus::EXPIRED], true);
    }
}
