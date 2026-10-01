<?php

declare(strict_types=1);

namespace App\Services\Offers;

use App\Enums\OfferStatus;
use App\Events\Offers\OfferExpired;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Offer;
use Illuminate\Support\Facades\DB;

/**
 * The single place offer state changes happen.
 *
 * Every transition runs inside a transaction that locks the ad first and
 * the offer second. Locking the ad serialises everything that competes for
 * one listing (new offers, acceptances, the ad being sold or removed), and
 * the fixed ad → offer order keeps concurrent transitions deadlock free.
 * The offer is re-read under the lock, so a stale model loaded before the
 * request raced another one can never act on an outdated status.
 */
class OfferTransitionService
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
     * Locks the offer's ad and the offer, refusing when the offer is no
     * longer open.
     *
     * @template TResult
     *
     * @param callable(Offer, Ad): TResult $callback
     * @return TResult
     */
    public function withLockedOffer(Offer $offer, callable $callback): mixed
    {
        return DB::transaction(function () use ($offer, $callback): mixed {
            $ad = $this->lockAd($offer->ad_id);

            /** @var Offer $locked */
            $locked = Offer::query()->whereKey($offer->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                throw new DomainException(ErrorCode::OFFER_NOT_PENDING);
            }

            return $callback($locked, $ad);
        });
    }

    /**
     * An ad takes new offers and acceptances only while it is publicly
     * listed and no other offer on it has been accepted.
     */
    public function assertAdIsOpenForOffers(Ad $ad): void
    {
        if ($ad->trashed() || ! $ad->isPubliclyListed()) {
            throw new DomainException(ErrorCode::OFFER_AD_NOT_ACTIVE);
        }

        $alreadyAgreed = Offer::query()
            ->where('ad_id', $ad->id)
            ->where('status', OfferStatus::ACCEPTED->value)
            ->exists();

        if ($alreadyAgreed) {
            throw new DomainException(ErrorCode::OFFER_AD_ALREADY_AGREED);
        }
    }

    public function moveTo(Offer $offer, OfferStatus $next): void
    {
        if (! $offer->status->canTransitionTo($next)) {
            throw new DomainException(ErrorCode::OFFER_NOT_PENDING);
        }

        $attributes = ['status' => $next];
        $timestampColumn = $next->timestampColumn();

        if ($timestampColumn !== null) {
            $attributes[$timestampColumn] = now();
        }

        $offer->forceFill($attributes)->save();
    }

    /**
     * Expires every open offer on the ad, except the one being accepted.
     * Callers must already hold the ad lock or be changing the ad itself.
     */
    public function expireOpenOffersOnAd(string $adId, ?string $exceptOfferId = null): void
    {
        DB::transaction(function () use ($adId, $exceptOfferId): void {
            $openOffers = Offer::query()
                ->where('ad_id', $adId)
                ->where('status', OfferStatus::PENDING->value)
                ->when($exceptOfferId !== null, fn ($query) => $query->whereKeyNot($exceptOfferId))
                ->lockForUpdate()
                ->get();

            $openOffers->each(fn (Offer $offer) => $this->expire($offer));
        });
    }

    /**
     * Expires the offer when its window has closed. Returns false when a
     * concurrent transition already moved it on.
     */
    public function expireIfDue(Offer $offer): bool
    {
        return DB::transaction(function () use ($offer): bool {
            /** @var Offer|null $locked */
            $locked = Offer::query()->whereKey($offer->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->status !== OfferStatus::PENDING || $locked->expires_at->isFuture()) {
                return false;
            }

            $this->expire($locked);

            return true;
        });
    }

    private function expire(Offer $offer): void
    {
        $this->moveTo($offer, OfferStatus::EXPIRED);

        DB::afterCommit(fn () => OfferExpired::dispatch($offer, $offer->buyer_id));
    }

    private function lockAd(string $adId): Ad
    {
        /** @var Ad $ad */
        $ad = Ad::withTrashed()->whereKey($adId)->lockForUpdate()->firstOrFail();
        $ad->loadMissing('user');

        return $ad;
    }
}
