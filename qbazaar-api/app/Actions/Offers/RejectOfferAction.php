<?php

declare(strict_types=1);

namespace App\Actions\Offers;

use App\Data\Messaging\ChatMessageDraft;
use App\Enums\ChatMessageKey;
use App\Enums\OfferStatus;
use App\Events\Offers\OfferRejected;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Offer;
use App\Models\User;
use App\Services\Messaging\ConversationMessageWriter;
use App\Services\Offers\OfferTransitionService;
use Illuminate\Support\Facades\DB;

/**
 * The responder declines an open offer.
 */
class RejectOfferAction
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

        return $this->transitions->withLockedOffer($offer, function (Offer $offer) use ($actor): Offer {
            $this->transitions->moveTo($offer, OfferStatus::REJECTED);

            $this->messages->append($offer->conversation, $actor, ChatMessageDraft::system(ChatMessageKey::OFFER_REJECTED));

            DB::afterCommit(fn () => OfferRejected::dispatch($offer, $offer->proposerId()));

            return $offer;
        });
    }
}
