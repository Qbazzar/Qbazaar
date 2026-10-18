<?php

declare(strict_types=1);

namespace App\Services\Admin\Moderation;

use App\Data\Moderation\ModerationResult;
use App\Enums\AdStatus;
use App\Models\Ad;
use App\Services\Ads\PendingReviewCounter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Lang;

/**
 * The ads waiting for review, oldest submission first, each measured against
 * the review SLA. The page is one indexed read (ads_status_submitted_idx keeps
 * it sorted) plus four eager loads whatever its size; the summary adds one
 * ranged count and the cached pending total.
 */
final class ReviewQueue
{
    public function __construct(
        private readonly PendingReviewCounter $pending,
    ) {}

    /**
     * @return Paginator<int, ReviewQueueRow>
     */
    public function page(SlaClock $clock, int $perPage): Paginator
    {
        return $this->pendingAds()
            ->with(['user:id,full_name,email', 'user.avatarMedia', 'category', 'primaryImage'])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->simplePaginate($perPage)
            ->withQueryString()
            ->through(fn (Ad $ad): ReviewQueueRow => $this->row($ad, $clock));
    }

    public function summary(SlaClock $clock): ReviewQueueSummary
    {
        $waiting = $this->pending->count();

        if ($waiting === 0) {
            return new ReviewQueueSummary(0, 0, null, null, null, $clock->targetHours);
        }

        $overdue = $this->pendingAds()
            ->where(fn (Builder $query) => $query
                ->where('submitted_at', '<=', $clock->overdueBefore())
                // Ads parked before submitted_at existed are the oldest of all.
                ->orWhereNull('submitted_at'))
            ->count();

        $oldest = $this->pendingAds()
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->first(['id', 'submitted_at', 'created_at']);

        $since = $oldest !== null ? $this->since($oldest) : null;

        return new ReviewQueueSummary(
            waiting: $waiting,
            overdue: $overdue,
            oldestSince: $since,
            oldestWaited: $since?->diffForHumans($clock->now, syntax: CarbonImmutable::DIFF_ABSOLUTE, short: true),
            oldestState: $since !== null ? $clock->stateFor($since) : null,
            targetHours: $clock->targetHours,
        );
    }

    /**
     * @return Builder<Ad>
     */
    private function pendingAds(): Builder
    {
        return Ad::query()->where('status', AdStatus::PENDING->value);
    }

    private function row(Ad $ad, SlaClock $clock): ReviewQueueRow
    {
        $since = $this->since($ad);
        $result = $ad->moderation_result;

        return new ReviewQueueRow(
            ad: $ad,
            since: $since,
            sla: $clock->stateFor($since),
            waited: $since->diffForHumans($clock->now, syntax: CarbonImmutable::DIFF_ABSOLUTE, short: true),
            clean: $result instanceof ModerationResult ? $result->clean : null,
            flags: $result instanceof ModerationResult ? array_map($this->flagLabel(...), $result->flags) : [],
        );
    }

    /** The label the ad page uses for an auto-moderation flag; an unknown flag shows as is. */
    private function flagLabel(string $flag): string
    {
        $key = 'admin.ad_review.auto_check_flags.' . $flag;

        return Lang::has($key) ? (string) __($key) : $flag;
    }

    private function since(Ad $ad): CarbonImmutable
    {
        return CarbonImmutable::instance($ad->submitted_at ?? $ad->created_at);
    }
}
