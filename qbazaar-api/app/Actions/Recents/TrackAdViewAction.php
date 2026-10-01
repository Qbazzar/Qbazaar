<?php

declare(strict_types=1);

namespace App\Actions\Recents;

use App\Models\Ad;
use App\Models\RecentView;
use App\Models\User;
use App\Services\Ads\Views\AdViewCounter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Lottery;

/**
 * Record an ad view for an authenticated user OR an anonymous client
 * identified by a stable session id.
 *
 *  - Throttled to one accepted view per (viewer, ad) per hour via an atomic
 *    Cache::add window, so repeats and bots are turned into cheap no-ops. We
 *    use add() rather than a lock so the window is a plain, flushable cache
 *    entry — a held lock survives Cache::flush() on some stores.
 *  - `ads.views_count` goes up once per accepted view, and at most once per
 *    IP per ad in the same window: anonymous callers choose their own
 *    X-Session-Id, so rotating it must not inflate the count. The count is
 *    buffered by {@see AdViewCounter} rather than written per view.
 *  - Only signed-in viewers get a stored history; guests keep theirs on the
 *    client, since the API never returned it. The history is trimmed to its
 *    cap on a fraction of writes, so most views cost one insert.
 */
class TrackAdViewAction
{
    /** Throttle window for repeat views of the same ad by the same viewer. */
    private const THROTTLE_TTL_SECONDS = 3600;

    public function __construct(private readonly AdViewCounter $views) {}

    public function execute(Ad $ad, ?User $user, ?string $sessionId, ?string $ip = null): bool
    {
        $viewerKey = $user !== null ? 'u:' . $user->id : ($sessionId !== null ? 's:' . $sessionId : null);

        if ($viewerKey === null) {
            // No identity at all — nothing to record. Caller will return 204.
            return false;
        }

        if (! Cache::add('view:' . $viewerKey . ':' . $ad->id, true, self::THROTTLE_TTL_SECONDS)) {
            return false;
        }

        if ($ip === null || Cache::add('view-ip:' . $ip . ':' . $ad->id, true, self::THROTTLE_TTL_SECONDS)) {
            $this->views->record($ad->id);
        }

        if ($user !== null) {
            $this->remember($user, $ad);
        }

        return true;
    }

    private function remember(User $user, Ad $ad): void
    {
        RecentView::query()->create([
            'user_id' => $user->id,
            'ad_id' => $ad->id,
            'viewed_at' => now(),
        ]);

        Lottery::odds(1, (int) config('qbazaar.ads.recent_views_trim_odds'))
            ->winner(fn () => $this->trimHistory($user->id))
            ->choose();
    }

    /**
     * Drop everything older than the newest `recent_views_cap` rows. Both
     * statements run on recently_viewed_user_viewed_idx.
     */
    private function trimHistory(string $userId): void
    {
        $oldestKept = RecentView::query()
            ->where('user_id', $userId)
            ->orderByDesc('viewed_at')
            ->offset((int) config('qbazaar.ads.recent_views_cap') - 1)
            ->value('viewed_at');

        if ($oldestKept === null) {
            return;
        }

        RecentView::query()
            ->where('user_id', $userId)
            ->where('viewed_at', '<', $oldestKept)
            ->delete();
    }
}
