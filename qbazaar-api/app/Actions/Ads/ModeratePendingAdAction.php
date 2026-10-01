<?php

declare(strict_types=1);

namespace App\Actions\Ads;

use App\Enums\AdStatus;
use App\Events\Ads\AdModerated;
use App\Models\Ad;
use Illuminate\Support\Facades\DB;

/**
 * Stores the auto-moderation triage hints on an ad waiting for review.
 *
 * The checks run before the row lock is taken, so the lock only covers a
 * single write. The result is dropped when the ad left review or was
 * resubmitted meanwhile; that submission queues its own run. Reviewers are
 * alerted after the first result of each submission only, so a re-run once
 * the image hashes land refreshes the hints without a second alert.
 */
class ModeratePendingAdAction
{
    public function __construct(
        private readonly ModerateAdAction $moderate,
    ) {}

    public function __invoke(string $adId): void
    {
        $ad = Ad::query()->find($adId);

        if ($ad?->status !== AdStatus::PENDING) {
            return;
        }

        $result = ($this->moderate)($ad);

        DB::transaction(function () use ($ad, $result): void {
            /** @var Ad|null $locked */
            $locked = Ad::query()->lockForUpdate()->find($ad->getKey());

            if ($locked === null || ! $this->isSameSubmission($locked, $ad)) {
                return;
            }

            $isFirstResult = $locked->moderation_result === null;

            // Quiet save: the hints are reviewer metadata, not a listing or search change.
            $locked->forceFill(['moderation_result' => $result])->saveQuietly();

            if ($isFirstResult) {
                DB::afterCommit(static fn () => AdModerated::dispatch($locked, $result));
            }
        });
    }

    private function isSameSubmission(Ad $locked, Ad $moderated): bool
    {
        return $locked->status === AdStatus::PENDING
            && $locked->submitted_at?->getTimestamp() === $moderated->submitted_at?->getTimestamp();
    }
}
