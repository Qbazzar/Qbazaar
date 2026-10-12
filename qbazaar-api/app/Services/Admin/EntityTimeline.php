<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * The newest activity-log rows about one record, shaped for the side-column
 * timeline. Costs two queries whatever the row count: the log rows (served by
 * the subject morph index) and one eager load of their causers.
 */
final class EntityTimeline
{
    public const DEFAULT_LIMIT = 8;

    private const MAX_LIMIT = 50;

    /** Tone per action, keyed by the event's last segment (admin route events end in the action). */
    private const TONES = [
        'approve' => 'success',
        'activate' => 'success',
        'unsuspend' => 'success',
        'ad_created' => 'success',
        'ad_restored' => 'success',
        'signed_up' => 'success',
        'reject' => 'danger',
        'suspend' => 'danger',
        'destroy' => 'danger',
        'suspend-ad' => 'danger',
        'ban-user' => 'danger',
        'ad_deleted' => 'danger',
        'deleted' => 'danger',
        'account_deactivated' => 'danger',
        'force-expire' => 'warning',
        'status_changed' => 'warning',
        'account_deletion_requested' => 'warning',
    ];

    /**
     * @param list<string>|null $events only these events, e.g. a status history
     * @return Collection<int, TimelineEntry>
     */
    public function forSubject(Model $subject, int $limit = self::DEFAULT_LIMIT, ?array $events = null): Collection
    {
        return Activity::query()
            ->whereMorphedTo('subject', $subject)
            ->when($events !== null, fn (Builder $query): Builder => $query->whereIn('event', $events ?? []))
            ->with('causer')
            ->latest('id')
            ->limit(max(1, min($limit, self::MAX_LIMIT)))
            ->get()
            ->map(fn (Activity $activity): TimelineEntry => new TimelineEntry(
                title: $this->titleOf($activity),
                actor: $this->actorOf($activity),
                at: CarbonImmutable::instance($activity->created_at ?? now()),
                tone: self::TONES[Str::afterLast((string) $activity->event, '.')] ?? 'neutral',
            ))
            ->values();
    }

    private function titleOf(Activity $activity): string
    {
        $event = (string) $activity->event;
        $key = 'admin.timeline.events.' . Str::chopStart($event, 'admin.');
        $line = $event === '' ? null : trans($key);

        if (is_string($line) && $line !== $key) {
            return $line;
        }

        // Generic admin entries describe themselves with the route name, which means nothing to a reader.
        return $activity->description !== '' && ! str_starts_with($activity->description, 'admin.')
            ? $activity->description
            : (string) __('admin.timeline.fallback');
    }

    private function actorOf(Activity $activity): ?string
    {
        $causer = $activity->causer;

        return match (true) {
            $causer instanceof User => $causer->full_name ?: $causer->email,
            $causer instanceof Model => class_basename($causer),
            $activity->causer_id === null => (string) __('admin.timeline.system'),
            default => null,
        };
    }
}
