<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ReportTarget;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

/**
 * Maps a `(ReportTarget, target_id)` tuple to a concrete model existence
 * lookup.
 *
 * Kept in its own class (rather than inline in the controller) because:
 *  - The Ad model is loaded via a dedicated query class in production; we
 *    don't want the report path to import the Ads sub-system directly.
 *  - The set of valid target types may grow (offer, review, …); adding a
 *    new case here is mechanical.
 *  - Unit tests can stub the resolver to exercise the controller's error
 *    paths without seeding every parent table.
 */
class ReportTargetResolver
{
    /**
     * @return bool true when the target row exists, false otherwise.
     */
    public function exists(ReportTarget $type, string $id): bool
    {
        return match ($type) {
            ReportTarget::AD => Ad::query()->whereKey($id)->exists(),
            ReportTarget::USER => User::query()->whereKey($id)->exists(),
            ReportTarget::CONVERSATION => Conversation::query()->whereKey($id)->exists(),
            ReportTarget::MESSAGE => Message::query()->whereKey($id)->exists(),
        };
    }
}
