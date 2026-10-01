<?php

declare(strict_types=1);

namespace App\Actions\Offers;

use App\Enums\MessageType;
use App\Enums\OfferParty;
use App\Enums\OfferStatus;
use App\Events\Offers\OfferCreated;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\User;
use App\Services\Messaging\ConversationMessageWriter;
use App\Services\Offers\OfferMessageFormatter;
use App\Services\Offers\OfferTransitionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Creates a buyer offer in a conversation together with its `type=offer`
 * chat bubble.
 *
 * Domain invariants (each throws a stable ErrorCode):
 *   - OFFER_OWN_AD            : buyer can't offer on their own ad.
 *   - MSG_CHAT_DISABLED       : the seller has turned chat off.
 *   - MSG_BLOCKED             : either side has blocked the other.
 *   - OFFER_AD_NOT_ACTIVE     : the ad is not publicly listed.
 *   - OFFER_AD_ALREADY_AGREED : another offer on the ad was accepted.
 *   - OFFER_ACTIVE_EXISTS     : one open offer per (buyer, ad).
 */
class MakeOfferAction
{
    public function __construct(
        private readonly OfferTransitionService $transitions,
        private readonly ConversationMessageWriter $messages,
        private readonly OfferMessageFormatter $formatter,
    ) {}

    public function __invoke(User $buyer, Conversation $conversation, float $amount, ?string $note): Offer
    {
        $conversation->loadMissing(['buyer', 'seller']);
        $seller = $conversation->seller;

        if ($buyer->id === $conversation->seller_id) {
            throw new DomainException(ErrorCode::OFFER_OWN_AD);
        }

        if (! $seller->privacySettings()->allow_chat) {
            throw new DomainException(ErrorCode::MSG_CHAT_DISABLED);
        }

        if ($buyer->hasBlocked($seller) || $seller->hasBlocked($buyer)) {
            throw new DomainException(ErrorCode::MSG_BLOCKED);
        }

        try {
            return $this->transitions->withLockedAd(
                $conversation->ad_id,
                fn (Ad $ad): Offer => $this->createOffer($ad, $buyer, $conversation, $amount, $note),
            );
        } catch (UniqueConstraintViolationException) {
            throw new DomainException(ErrorCode::OFFER_ACTIVE_EXISTS);
        }
    }

    private function createOffer(Ad $ad, User $buyer, Conversation $conversation, float $amount, ?string $note): Offer
    {
        $this->transitions->assertAdIsOpenForOffers($ad);
        $this->ensureNoOpenOffer($buyer, $ad);

        $message = $this->messages->append(
            $conversation,
            $buyer,
            $this->formatter->body($amount, $note),
            MessageType::OFFER,
        );

        /** @var Offer $offer */
        $offer = Offer::query()->create([
            'conversation_id' => $conversation->id,
            'ad_id' => $ad->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $conversation->seller_id,
            'proposed_by' => OfferParty::BUYER,
            'message_id' => $message->id,
            'amount' => $amount,
            'currency' => config('qbazaar.default_currency', 'QAR'),
            'note' => $note,
            'status' => OfferStatus::PENDING,
            'expires_at' => now()->addDays((int) config('qbazaar.offers.expiry_days', 7)),
        ]);

        $message->setRelation('offer', $offer);

        DB::afterCommit(fn () => OfferCreated::dispatch($offer, $conversation->seller_id));

        return $offer;
    }

    /**
     * An open offer whose window already closed only waits for the daily
     * sweep, so it is expired here instead of blocking the new one.
     */
    private function ensureNoOpenOffer(User $buyer, Ad $ad): void
    {
        /** @var Offer|null $open */
        $open = Offer::query()
            ->where('buyer_id', $buyer->id)
            ->where('ad_id', $ad->id)
            ->where('status', OfferStatus::PENDING->value)
            ->lockForUpdate()
            ->first();

        if ($open === null) {
            return;
        }

        if ($open->isActive() || ! $this->transitions->expireIfDue($open)) {
            throw new DomainException(ErrorCode::OFFER_ACTIVE_EXISTS);
        }
    }
}
