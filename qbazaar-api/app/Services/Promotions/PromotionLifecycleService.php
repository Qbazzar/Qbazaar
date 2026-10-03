<?php

declare(strict_types=1);

namespace App\Services\Promotions;

use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerReference;
use App\Enums\LedgerReferenceType;
use App\Enums\PromotionPaymentMethod;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Events\Promotions\PromotionActivated;
use App\Events\Promotions\PromotionExpired;
use App\Events\Promotions\PromotionRejected;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Jobs\Catalog\WarmCatalogCacheJob;
use App\Models\Ad;
use App\Models\AdPromotion;
use App\Models\User;
use App\Services\Ledger\LedgerRecipes;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of `ad_promotions.status` and `ads.promotion_rank`.
 *
 * Every step runs in one database transaction that locks the ad, then the
 * promotion, then (through the ledger) the touched accounts: the same
 * ad-first order orders and offers use. The ledger posting commits with the
 * status change, and events fire after the commit.
 */
class PromotionLifecycleService
{
    public function __construct(
        private readonly PromotionCatalog $catalog,
        private readonly LedgerRecipes $recipes,
    ) {}

    /**
     * Buys a promotion at the current catalogue price. Paid from the wallet
     * it starts at once; by bank transfer it waits for an admin.
     */
    public function purchase(User $owner, Ad $ad, PromotionType $type, PromotionPaymentMethod $method, ?string $transferReference): AdPromotion
    {
        $offer = $this->catalog->offerFor($type);

        return $this->commit(function () use ($owner, $ad, $offer, $method, $transferReference): array {
            $lockedAd = $this->lockAd($ad->id);

            if ($lockedAd === null || $lockedAd->user_id !== $owner->id) {
                throw new DomainException(ErrorCode::AD_NOT_FOUND);
            }

            if (! $lockedAd->isPubliclyListed()) {
                throw new DomainException(ErrorCode::AD_NOT_ACTIVE);
            }

            $promotion = new AdPromotion;
            $promotion->forceFill([
                'ad_id' => $lockedAd->id,
                'user_id' => $owner->id,
                'type' => $offer->type,
                'status' => PromotionStatus::PENDING_PAYMENT,
                'payment_method' => $method,
                'price' => $offer->price,
                'currency' => $offer->currency,
                'duration_days' => $offer->durationDays,
                'transfer_reference' => $method === PromotionPaymentMethod::BANK_TRANSFER ? $transferReference : null,
            ]);

            $this->claimSlot($promotion);

            if ($method === PromotionPaymentMethod::BANK_TRANSFER) {
                return [$promotion, null];
            }

            $this->recipes->purchasePromotionFromWallet($owner->id, $promotion->price, $this->referenceOf($promotion), LedgerActor::user($owner));

            return [$promotion, $this->activate($promotion, $lockedAd)];
        });
    }

    /**
     * The admin saw the transfer arrive: the revenue is booked against the
     * bank and the promotion starts now, for its full duration.
     */
    public function confirmTransfer(AdPromotion $promotion, User $admin): AdPromotion
    {
        return $this->withLockedPromotion($promotion, function (?Ad $lockedAd, AdPromotion $locked) use ($admin): Closure {
            $this->assertCanMove($locked, PromotionStatus::ACTIVE);
            $locked->forceFill(['reviewed_by' => $admin->id]);

            $this->recipes->purchasePromotionByBankTransfer($locked->price, $this->referenceOf($locked), LedgerActor::admin($admin));

            return $this->activate($locked, $lockedAd);
        });
    }

    /**
     * No money is booked for a rejected transfer, so nothing is reversed.
     */
    public function rejectTransfer(AdPromotion $promotion, User $admin, ?string $reason): AdPromotion
    {
        return $this->withLockedPromotion($promotion, function (?Ad $lockedAd, AdPromotion $locked) use ($admin, $reason): Closure {
            $this->moveTo($locked, PromotionStatus::REJECTED, [
                'reviewed_by' => $admin->id,
                'rejection_reason' => $reason,
            ]);

            return fn () => PromotionRejected::dispatch($locked);
        });
    }

    /**
     * Ends a promotion whose time is up. Re-checked under the lock, so a
     * retried or duplicate sweep changes nothing.
     */
    public function expireIfDue(AdPromotion $promotion): AdPromotion
    {
        return $this->withLockedPromotion($promotion, function (?Ad $lockedAd, AdPromotion $locked): ?Closure {
            if ($locked->status !== PromotionStatus::ACTIVE || $locked->ends_at === null || $locked->ends_at->isFuture()) {
                return null;
            }

            $this->moveTo($locked, PromotionStatus::EXPIRED);
            $this->refreshRank($lockedAd);

            return function () use ($locked): void {
                PromotionExpired::dispatch($locked);
                WarmCatalogCacheJob::dispatch();
            };
        });
    }

    private function activate(AdPromotion $promotion, ?Ad $lockedAd): Closure
    {
        $this->moveTo($promotion, PromotionStatus::ACTIVE, [
            'starts_at' => now(),
            'ends_at' => now()->addDays($promotion->duration_days),
        ]);
        $this->refreshRank($lockedAd);

        return function () use ($promotion): void {
            PromotionActivated::dispatch($promotion);
            WarmCatalogCacheJob::dispatch();
        };
    }

    /**
     * Caches the strongest active promotion on the ad. Saving through the
     * model lets Scout re-index the ad and the listing caches react.
     */
    private function refreshRank(?Ad $lockedAd): void
    {
        if ($lockedAd === null) {
            return;
        }

        $rank = AdPromotion::query()
            ->where('ad_id', $lockedAd->id)
            ->where('status', PromotionStatus::ACTIVE->value)
            ->pluck('type')
            ->map(fn (PromotionType $type): int => $type->rank())
            ->max() ?? 0;

        if ($lockedAd->promotion_rank !== $rank) {
            $lockedAd->forceFill(['promotion_rank' => $rank])->save();
        }
    }

    /**
     * The unique open slot settles two concurrent purchases of the same
     * type on one ad: the second insert fails and is reported, not charged.
     */
    private function claimSlot(AdPromotion $promotion): void
    {
        try {
            $promotion->save();
        } catch (UniqueConstraintViolationException) {
            throw new DomainException(ErrorCode::PROMOTION_ALREADY_OPEN);
        }
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function moveTo(AdPromotion $promotion, PromotionStatus $next, array $attributes = []): void
    {
        $this->assertCanMove($promotion, $next);

        $column = $next->timestampColumn();

        if ($column !== null) {
            $attributes[$column] = now();
        }

        $promotion->forceFill(['status' => $next, ...$attributes])->save();
    }

    private function assertCanMove(AdPromotion $promotion, PromotionStatus $next): void
    {
        if (! $promotion->status->canTransitionTo($next)) {
            throw new DomainException(ErrorCode::PROMOTION_INVALID_TRANSITION, details: [
                'from' => $promotion->status->value,
                'to' => $next->value,
            ]);
        }
    }

    private function referenceOf(AdPromotion $promotion): LedgerReference
    {
        return new LedgerReference(LedgerReferenceType::PROMOTION, $promotion->id);
    }

    /**
     * Locks the ad, then the promotion, and runs the change on the locked
     * copy. The caller's instance is refreshed with the result.
     *
     * @param Closure(Ad|null, AdPromotion): (Closure|null) $change returns the post-commit side effect, if any
     */
    private function withLockedPromotion(AdPromotion $promotion, Closure $change): AdPromotion
    {
        $locked = $this->commit(function () use ($promotion, $change): array {
            $lockedAd = $this->lockAd($promotion->ad_id);
            $locked = AdPromotion::query()->whereKey($promotion->id)->lockForUpdate()->firstOrFail();

            return [$locked, $change($lockedAd, $locked)];
        });

        $promotion->setRawAttributes($locked->getAttributes(), true);

        return $locked;
    }

    /**
     * @param Closure(): array{0: AdPromotion, 1: Closure|null} $work returns the promotion and its post-commit side effect
     */
    private function commit(Closure $work): AdPromotion
    {
        return DB::transaction(function () use ($work): AdPromotion {
            [$promotion, $afterCommit] = $work();

            if ($afterCommit !== null) {
                DB::afterCommit($afterCommit);
            }

            return $promotion;
        });
    }

    private function lockAd(?string $adId): ?Ad
    {
        if ($adId === null) {
            return null;
        }

        /** @var Ad|null */
        return Ad::query()->whereKey($adId)->lockForUpdate()->first();
    }
}
