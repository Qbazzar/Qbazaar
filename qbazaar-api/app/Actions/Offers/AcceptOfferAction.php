<?php

declare(strict_types=1);

namespace App\Actions\Offers;

use App\Enums\MessageType;
use App\Enums\OfferStatus;
use App\Events\Offers\OfferAccepted;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Offer;
use App\Models\User;
use App\Services\Messaging\ConversationMessageWriter;
use App\Services\Offers\OfferTransitionService;
use Illuminate\Support\Facades\DB;

/**
 * The responder accepts an open offer. Every other open offer on the ad is
 * expired in the same transaction, so an ad can never end up with two
 * accepted offers.
 */
class AcceptOfferAction
{
    public function __construct(
        private readonly OfferTransitionService $transitions,
        private readonly ConversationMessageWriter $messages,
    ) {}

    public function __invoke(User $actor, Offer $offer): Offer
    {
        if ($actor->id !== $offer->responderId()) {
            throw new DomainException(ErrorCode::OFFER_FORBIDDEN);
        }

        return $this->transitions->withLockedOffer($offer, function (Offer $offer, Ad $ad) use ($actor): Offer {
            $this->transitions->assertAdIsOpenForOffers($ad);
            $this->transitions->moveTo($offer, OfferStatus::ACCEPTED);
            $this->transitions->expireOpenOffersOnAd($ad->id, exceptOfferId: $offer->id);

            $this->messages->append($offer->conversation, $actor, 'Offer accepted', MessageType::SYSTEM);

            DB::afterCommit(fn () => OfferAccepted::dispatch($offer, $offer->proposerId()));

            return $offer;
        });
    }
}
