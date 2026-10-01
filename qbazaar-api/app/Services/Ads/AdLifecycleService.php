<?php

declare(strict_types=1);

namespace App\Services\Ads;

use App\Actions\Ads\ModerateAdAction;
use App\Data\Moderation\ModerationResult;
use App\Enums\AdStatus;
use App\Events\Ads\AdApproved;
use App\Events\Ads\AdExpired;
use App\Events\Ads\AdRejected;
use App\Events\Ads\AdRenewed;
use App\Events\Ads\AdSubmittedForReview;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of `ads.status`. Sellers, admins and scheduled jobs all go
 * through here, so every change is checked against AdStatus::canTransitionTo()
 * on a locked row and its events fire only after the commit.
 */
class AdLifecycleService
{
    public function __construct(
        private readonly ModerateAdAction $moderate,
    ) {}

    /**
     * Park the ad in PENDING for manual review. Auto-moderation only produces
     * triage hints for the reviewer. An ad already waiting is left untouched
     * so a repeated publish does not notify the reviewers again.
     */
    public function submitForReview(Ad $ad): Ad
    {
        return $this->transition($ad, AdStatus::PENDING, function (Ad $locked): ?Closure {
            if ($locked->status === AdStatus::PENDING) {
                return null;
            }

            $result = ($this->moderate)($locked);

            $this->apply($locked, AdStatus::PENDING, [
                'published_at' => null,
                'expires_at' => null,
                'submitted_at' => now(),
            ]);

            return fn () => AdSubmittedForReview::dispatch($locked, $result);
        }, allowSameStatus: true);
    }

    public function approve(Ad $ad): Ad
    {
        return $this->transition($ad, AdStatus::ACTIVE, function (Ad $locked): Closure {
            $this->goLive($locked);

            return fn () => AdApproved::dispatch($locked);
        }, from: AdStatus::PENDING);
    }

    public function reject(Ad $ad, string $notes): Ad
    {
        return $this->transition($ad, AdStatus::REJECTED, function (Ad $locked) use ($notes): Closure {
            $this->apply($locked, AdStatus::REJECTED, [
                'published_at' => null,
                'expires_at' => null,
            ]);

            $result = ModerationResult::rejected(['admin_manual'], ['admin_notes' => $notes]);

            return fn () => AdRejected::dispatch($locked, $result);
        });
    }

    public function block(Ad $ad): Ad
    {
        return $this->transition($ad, AdStatus::BLOCKED, function (Ad $locked): null {
            $this->apply($locked, AdStatus::BLOCKED);

            return null;
        });
    }

    public function unblock(Ad $ad): Ad
    {
        return $this->transition($ad, AdStatus::ACTIVE, function (Ad $locked): Closure {
            $this->goLive($locked);

            return fn () => AdApproved::dispatch($locked);
        }, from: AdStatus::BLOCKED);
    }

    public function expire(Ad $ad): Ad
    {
        return $this->transition($ad, AdStatus::EXPIRED, function (Ad $locked): Closure {
            $this->apply($locked, AdStatus::EXPIRED);

            return fn () => AdExpired::dispatch($locked);
        });
    }

    public function markSold(Ad $ad): Ad
    {
        return $this->transition($ad, AdStatus::SOLD, function (Ad $locked): null {
            $this->apply($locked, AdStatus::SOLD);

            return null;
        });
    }

    /**
     * Extend the expiry window by another lifetime. A live ad keeps its
     * status; an expired one goes straight back to ACTIVE.
     */
    public function renew(Ad $ad): Ad
    {
        return $this->transition($ad, AdStatus::ACTIVE, function (Ad $locked): Closure {
            $base = $locked->expires_at?->isFuture() === true ? $locked->expires_at : now();

            $this->apply($locked, AdStatus::ACTIVE, [
                'expires_at' => $base->copy()->addDays($this->lifetimeDays()),
                'expiring_notified_at' => null,
            ]);

            return fn () => AdRenewed::dispatch($locked);
        }, from: AdStatus::EXPIRED, allowSameStatus: true);
    }

    /**
     * @param Closure(Ad): (Closure|null) $change returns the post-commit side effect, if any
     * @param AdStatus|null $from narrows the enum rule when only one source status is valid
     */
    private function transition(
        Ad $ad,
        AdStatus $target,
        Closure $change,
        ?AdStatus $from = null,
        bool $allowSameStatus = false,
    ): Ad {
        $locked = DB::transaction(function () use ($ad, $target, $change, $from, $allowSameStatus): Ad {
            /** @var Ad $locked */
            $locked = Ad::query()->lockForUpdate()->findOrFail($ad->getKey());

            $this->guard($locked->status, $target, $from, $allowSameStatus);

            $afterCommit = $change($locked);

            if ($afterCommit !== null) {
                DB::afterCommit($afterCommit);
            }

            return $locked;
        });

        $ad->setRawAttributes($locked->getAttributes(), true);

        return $locked;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function apply(Ad $ad, AdStatus $status, array $attributes = []): void
    {
        $ad->forceFill(['status' => $status, ...$attributes])->save();
    }

    private function goLive(Ad $ad): void
    {
        $this->apply($ad, AdStatus::ACTIVE, [
            'published_at' => now(),
            'expires_at' => now()->addDays($this->lifetimeDays()),
            'expiring_notified_at' => null,
        ]);
    }

    private function guard(AdStatus $current, AdStatus $target, ?AdStatus $from, bool $allowSameStatus): void
    {
        $allowed = $current === $target
            ? $allowSameStatus
            : $current->canTransitionTo($target) && ($from === null || $current === $from);

        if (! $allowed) {
            throw new DomainException(
                ErrorCode::AD_INVALID_TRANSITION,
                details: ['from' => $current->value, 'to' => $target->value],
            );
        }
    }

    private function lifetimeDays(): int
    {
        return (int) config('qbazaar.ads.lifetime_days');
    }
}
