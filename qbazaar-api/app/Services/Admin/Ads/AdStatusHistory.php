<?php

declare(strict_types=1);

namespace App\Services\Admin\Ads;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\User;
use App\Services\Admin\TimelineEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * An ad's status history for the admin side column: its creation, every
 * status it moved through named after the move (approved, suspended, sent for
 * review…) and featuring, optionally with content edits.
 *
 * Status moves are read from AdObserver's rows, which record every change
 * whoever made it. The admin audit rows of the same requests are left out, so
 * an approval never shows twice. Two queries whatever the history length.
 */
final class AdStatusHistory
{
    public const DEFAULT_LIMIT = 8;

    private const EVENTS = ['ad_created', 'status_changed', 'admin.ads.feature'];

    private const EDIT_EVENTS = ['ad_updated'];

    /**
     * Moves a seller can never make. Queued jobs (automatic review, expiry)
     * log the owner as causer, so the system made these when that is the case.
     */
    private const STAFF_OR_SYSTEM_MOVES = ['approved', 'rejected', 'blocked', 'unblocked', 'expired'];

    /**
     * @return Collection<int, TimelineEntry>
     */
    public function forAd(Ad $ad, int $limit = self::DEFAULT_LIMIT, bool $withEdits = false): Collection
    {
        return Activity::query()
            ->whereMorphedTo('subject', $ad)
            ->whereIn('event', $withEdits ? [...self::EVENTS, ...self::EDIT_EVENTS] : self::EVENTS)
            ->with('causer')
            ->latest('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (Activity $activity): TimelineEntry => $this->entryFor($activity, $ad))
            ->values();
    }

    private function entryFor(Activity $activity, Ad $ad): TimelineEntry
    {
        $move = $activity->event === 'status_changed' ? $this->moveOf($activity) : null;

        return new TimelineEntry(
            title: $move !== null
                ? (string) __("admin.ad_review.history.{$move['key']}")
                : $this->eventTitle((string) $activity->event),
            actor: $this->actorOf($activity, $ad, $move['key'] ?? null),
            at: CarbonImmutable::instance($activity->created_at ?? now()),
            tone: $move['tone'] ?? 'neutral',
        );
    }

    /**
     * The move a status_changed row records, named after it, or null when its new status is unknown.
     *
     * @return array{key: string, tone: string}|null
     */
    private function moveOf(Activity $activity): ?array
    {
        $to = AdStatus::tryFrom((string) $activity->getProperty('new.status'));
        $from = AdStatus::tryFrom((string) $activity->getProperty('old.status'));

        if ($to === null) {
            return null;
        }

        $key = match (true) {
            $to === AdStatus::ACTIVE && $from === AdStatus::PENDING => 'approved',
            $to === AdStatus::ACTIVE && $from === AdStatus::BLOCKED => 'unblocked',
            $to === AdStatus::ACTIVE && $from === AdStatus::EXPIRED => 'renewed',
            default => $to->value,
        };

        // Timeline dots come in four tones; "sold" (info) reads as a neutral step.
        $tone = in_array($to->tone(), TimelineEntry::TONES, true) ? $to->tone() : 'neutral';

        return ['key' => $key, 'tone' => $tone];
    }

    private function eventTitle(string $event): string
    {
        return (string) __('admin.timeline.events.' . Str::chopStart($event, 'admin.'));
    }

    private function actorOf(Activity $activity, Ad $ad, ?string $move): string
    {
        $causer = $activity->causer;
        $isSeller = $causer instanceof User && $causer->getKey() === $ad->user_id;

        return match (true) {
            ! $causer instanceof User => (string) __('admin.timeline.system'),
            $isSeller && in_array($move, self::STAFF_OR_SYSTEM_MOVES, true) => (string) __('admin.timeline.system'),
            $isSeller => (string) __('admin.ad_review.history.seller'),
            default => $causer->full_name ?: $causer->email,
        };
    }
}
