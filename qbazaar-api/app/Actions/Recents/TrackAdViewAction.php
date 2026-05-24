<?php

declare(strict_types=1);

namespace App\Actions\Recents;

use App\Models\Ad;
use App\Models\RecentView;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Record an ad view for an authenticated user OR an anonymous client
 * identified by a stable session id.
 *
 *  - Throttled to one row per (viewer, ad) per hour via Cache::lock.
 *    Bots hammering the endpoint are turned into no-ops cheaply, before
 *    the row insert and counter increment land. We DO NOT release the
 *    lock — its TTL is the throttle window.
 *  - Caps stored history at 50 rows per user. The cap-cleanup runs in
 *    the same transaction as the insert so a crash can never leave the
 *    history bloated. Anonymous histories are not capped here; we let
 *    the future PruneRecentViewsJob handle session-id pruning.
 *  - The denormalised `ads.views_count` is incremented once per
 *    accepted (non-throttled) view so feed cards can render the
 *    headline count without a join.
 */
class TrackAdViewAction
{
    /** History cap per authenticated user. */
    private const MAX_ROWS_PER_USER = 50;

    /** Throttle window for repeat views of the same ad by the same viewer. */
    private const THROTTLE_TTL_SECONDS = 3600;

    public function execute(Ad $ad, ?User $user, ?string $sessionId): bool
    {
        $viewerKey = $user !== null ? 'u:' . $user->id : ($sessionId !== null ? 's:' . $sessionId : null);

        if ($viewerKey === null) {
            // No identity at all — nothing to record. Caller will return 204.
            return false;
        }

        $lock = Cache::lock(
            'view:' . $viewerKey . ':' . $ad->id,
            self::THROTTLE_TTL_SECONDS,
        );

        if (! $lock->get()) {
            // Within the throttle window — silent no-op.
            return false;
        }

        DB::transaction(function () use ($ad, $user, $sessionId): void {
            RecentView::query()->create([
                'user_id' => $user?->id,
                'session_id' => $user === null ? $sessionId : null,
                'ad_id' => $ad->id,
                'viewed_at' => now(),
            ]);

            Ad::query()->where('id', $ad->id)->increment('views_count');

            if ($user !== null) {
                $this->capUserHistory($user->id);
            }
        });

        return true;
    }

    /**
     * Drop the oldest rows so only the most recent 50 remain for this user.
     *
     * The doubly-nested SELECT is required by MySQL — it can't read from
     * the same table it's deleting from in a single statement.
     */
    private function capUserHistory(string $userId): void
    {
        $keepIds = RecentView::query()
            ->where('user_id', $userId)
            ->orderByDesc('viewed_at')
            ->limit(self::MAX_ROWS_PER_USER)
            ->pluck('id')
            ->all();

        if (count($keepIds) < self::MAX_ROWS_PER_USER) {
            return;
        }

        RecentView::query()
            ->where('user_id', $userId)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }
}
