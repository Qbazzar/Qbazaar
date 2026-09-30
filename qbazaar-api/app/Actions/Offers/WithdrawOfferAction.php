<?php

declare(strict_types=1);

namespace App\Actions\Offers;

use App\Enums\MessageType;
use App\Enums\OfferStatus;
use App\Events\Offers\OfferWithdrawn;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Offer;
use App\Models\User;
use App\Services\Messaging\ConversationMessageWriter;
use App\Services\Offers\OfferTransitionService;
use Illuminate\Support\Facades\DB;

/**
 * The proposer pulls their own open offer before the other side responds.
 */
class WithdrawOfferAction
{
    public function __construct(
        private readonly OfferTransitionService $transitions,
        private readonly ConversationMessageWriter $messages,
    ) {}

    public function __invoke(User $actor, Offer $offer): Offer
    {
        if ($actor->id !== $offer->proposerId()) {
            throw new DomainException(ErrorCode::OFFER_FORBIDDEN);
        }

        return $this->transitions->withLockedOffer($offer, function (Offer $offer) use ($actor): Offer {
            $this->transitions->moveTo($offer, OfferStatus::WITHDRAWN);

            $this->messages->append($offer->conversation, $actor, 'Offer withdrawn', MessageType::SYSTEM);

            DB::afterCommit(fn () => OfferWithdrawn::dispatch($offer, $offer->responderId()));

            return $offer;
        });
    }
}
