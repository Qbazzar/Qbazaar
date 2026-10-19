<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Data\Moderation\SanctionReasonInput;
use App\Enums\AdStatus;
use App\Exceptions\DomainException;
use App\Models\Ad;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Ads\AdLifecycleService;
use Closure;
use Illuminate\Database\Eloquent\Collection;

/**
 * Approves or rejects the ads selected on the review queue. Each ad still goes
 * through AdLifecycleService on its own locked row, so its events, the seller's
 * notification and the status guard are exactly those of the single-ad
 * buttons; an ad another reviewer handled meanwhile is skipped, not failed.
 *
 * The selection is read in one query and capped by the request
 * (bulk_action_max), so the per-ad writes are bounded.
 */
class BulkAdReview
{
    public function __construct(
        private readonly AdLifecycleService $lifecycle,
        private readonly AdminAuditLogger $audit,
    ) {}

    /**
     * @param list<string> $adIds
     */
    public function approve(User $staff, array $adIds): BulkReviewOutcome
    {
        return $this->each($adIds, function (Ad $ad) use ($staff): void {
            $this->lifecycle->approve($ad);
            $this->audit->record($staff, 'admin.ads.approve', $ad, ['bulk' => true]);
        });
    }

    /**
     * @param list<string> $adIds
     */
    public function reject(User $staff, array $adIds, SanctionReasonInput $input): BulkReviewOutcome
    {
        return $this->each($adIds, function (Ad $ad) use ($staff, $input): void {
            $this->lifecycle->reject($ad, $input->summary(), $input->reason);
            $this->audit->record($staff, 'admin.ads.reject', $ad, [...$input->toAudit(), 'bulk' => true]);
        });
    }

    /**
     * @param list<string> $adIds
     * @param Closure(Ad): void $review
     */
    private function each(array $adIds, Closure $review): BulkReviewOutcome
    {
        $pending = $this->pendingAmong($adIds);
        $done = 0;

        foreach ($pending as $ad) {
            try {
                $review($ad);
                $done++;
            } catch (DomainException) {
                // Another reviewer moved it on between the read and the lock.
            }
        }

        return new BulkReviewOutcome($done, count($adIds) - $done);
    }

    /**
     * @param list<string> $adIds
     * @return Collection<int, Ad>
     */
    private function pendingAmong(array $adIds): Collection
    {
        return Ad::query()
            ->whereIn('id', $adIds)
            ->where('status', AdStatus::PENDING->value)
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();
    }
}
