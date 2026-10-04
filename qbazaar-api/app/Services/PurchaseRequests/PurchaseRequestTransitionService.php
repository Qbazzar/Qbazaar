<?php

declare(strict_types=1);

namespace App\Services\PurchaseRequests;

use App\Enums\AdType;
use App\Enums\PurchaseRequestStatus;
use App\Events\PurchaseRequests\PurchaseRequestCancelled;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Order;
use App\Models\PurchaseRequest;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * The single place purchase request state changes happen.
 *
 * Every change runs in a transaction that locks the ad first and the request
 * second: the same ad-first order the offer and order flows use, so a "Buy
 * Now", an offer and an order on one ad are serialised and never deadlock.
 * The request is re-read under the lock, so a stale copy never acts on an
 * outdated status.
 */
class PurchaseRequestTransitionService
{
    /**
     * @template TResult
     *
     * @param callable(Ad): TResult $callback
     * @return TResult
     */
    public function withLockedAd(string $adId, callable $callback): mixed
    {
        return DB::transaction(fn (): mixed => $callback($this->lockAd($adId)));
    }

    /**
     * Locks the request's ad and the request, refusing when it is no longer
     * pending.
     *
     * @template TResult
     *
     * @param callable(PurchaseRequest, Ad): TResult $callback
     * @return TResult
     */
    public function withLockedPendingRequest(PurchaseRequest $request, callable $callback): mixed
    {
        return DB::transaction(function () use ($request, $callback): mixed {
            $ad = $this->lockAd($request->ad_id);

            /** @var PurchaseRequest $locked */
            $locked = PurchaseRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== PurchaseRequestStatus::PENDING) {
                throw new DomainException(ErrorCode::PURCHASE_REQUEST_NOT_PENDING);
            }

            return $callback($locked, $ad);
        });
    }

    /**
     * An ad can be bought while it is publicly listed, offers something at a
     * fixed price and holds no open order.
     */
    public function assertAdIsPurchasable(Ad $ad): void
    {
        $hasPrice = $ad->price !== null && Money::isPositive($ad->price) && ! $ad->price_type->requiresNullPrice();

        if ($ad->trashed() || ! $ad->isPubliclyListed() || $ad->ad_type !== AdType::OFFERING || ! $hasPrice) {
            throw new DomainException(ErrorCode::PURCHASE_REQUEST_AD_NOT_AVAILABLE);
        }

        if (Order::query()->where('ad_id', $ad->id)->active()->exists()) {
            throw new DomainException(ErrorCode::ORDER_AD_HAS_ACTIVE_ORDER);
        }
    }

    public function assertQuantityAvailable(Ad $ad, int $quantity): void
    {
        if (! $ad->offersQuantity($quantity)) {
            throw new DomainException(ErrorCode::PURCHASE_QUANTITY_UNAVAILABLE, details: ['available' => $ad->quantity]);
        }
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function moveTo(PurchaseRequest $request, PurchaseRequestStatus $next, array $attributes = []): void
    {
        if (! $request->status->canTransitionTo($next)) {
            throw new DomainException(ErrorCode::PURCHASE_REQUEST_NOT_PENDING);
        }

        $column = $next->timestampColumn();

        if ($column !== null) {
            $attributes[$column] = now();
        }

        $request->forceFill(['status' => $next, ...$attributes])->save();
    }

    /**
     * Cancels every pending request on the ad, except the one being
     * accepted. Callers must hold the ad lock or be changing the ad itself.
     * A NULL `cancelled_by` marks the platform as the closer.
     */
    public function cancelOpenRequestsOnAd(string $adId, ?string $exceptRequestId = null): void
    {
        DB::transaction(function () use ($adId, $exceptRequestId): void {
            PurchaseRequest::query()
                ->where('ad_id', $adId)
                ->where('is_open', true)
                ->when($exceptRequestId !== null, fn ($query) => $query->whereKeyNot($exceptRequestId))
                ->lockForUpdate()
                ->get()
                ->each(function (PurchaseRequest $request): void {
                    $this->moveTo($request, PurchaseRequestStatus::CANCELLED);

                    DB::afterCommit(fn () => PurchaseRequestCancelled::dispatch($request, $request->buyer_id));
                });
        });
    }

    private function lockAd(string $adId): Ad
    {
        /** @var Ad */
        return Ad::withTrashed()->whereKey($adId)->lockForUpdate()->firstOrFail();
    }
}
