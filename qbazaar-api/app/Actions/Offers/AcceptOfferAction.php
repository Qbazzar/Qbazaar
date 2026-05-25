<?php

declare(strict_types=1);

namespace App\Actions\Offers;

use App\Enums\MessageType;
use App\Enums\OfferStatus;
use App\Events\Messaging\MessageSent;
use App\Events\Offers\OfferAccepted;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seller accepts a pending offer.
 *
 * Guards:
 *  - OFFER_FORBIDDEN  : caller is not the recipient seller.
 *  - OFFER_NOT_PENDING: offer already terminal OR expiry timestamp passed.
 *
 * Inside the transaction we:
 *   1. Stamp the offer ACCEPTED + accepted_at = now().
 *   2. Withdraw any sibling PENDING offers from the same buyer on the
 *      same ad. The active-offer guard in MakeOfferAction should already
 *      prevent siblings from existing, but this is a defence-in-depth
 *      sweep for any historical row that pre-dates the constraint.
 *   3. Drop a `type=system` chat line so the transcript reads "Offer
 *      accepted" inline.
 *   4. Refresh conversation.last_message_at/preview.
 *
 * Broadcasts (deferred to afterCommit so subscribers don't race the DB):
 *   - MessageSent (system bubble) → existing chat channel.
 *   - OfferAccepted             → conversation + buyer's user channel.
 */
class AcceptOfferAction
{
    public function __invoke(User $seller, Offer $offer): Offer
    {
        if ($seller->id !== $offer->seller_id) {
            throw new DomainException(ErrorCode::OFFER_FORBIDDEN);
        }

        if (! $offer->isActive()) {
            throw new DomainException(ErrorCode::OFFER_NOT_PENDING);
        }

        $offer->loadMissing('conversation');
        $conversation = $offer->conversation;
        $body = 'Offer accepted';

        DB::transaction(function () use ($offer, $conversation, $body): void {
            $offer->forceFill([
                'status' => OfferStatus::ACCEPTED->value,
                'accepted_at' => now(),
            ])->save();

            // Defence-in-depth: any sibling PENDING offers from the same
            // (buyer, ad) tuple get pulled. The active-offer guard in
            // MakeOfferAction prevents this in normal flow.
            Offer::query()
                ->where('buyer_id', $offer->buyer_id)
                ->where('ad_id', $offer->ad_id)
                ->where('status', OfferStatus::PENDING->value)
                ->where('id', '!=', $offer->id)
                ->update([
                    'status' => OfferStatus::WITHDRAWN->value,
                    'withdrawn_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->appendSystemBubble($conversation, $body);
        });

        $offer->refresh();
        $buyer = $offer->buyer;

        DB::afterCommit(function () use ($offer, $buyer): void {
            OfferAccepted::dispatch($offer, $buyer->id);
        });

        return $offer;
    }

    private function appendSystemBubble(Conversation $conversation, string $body): void
    {
        /** @var Message $message */
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $conversation->seller_id,
            'body' => $body,
            'type' => MessageType::SYSTEM->value,
        ]);

        $conversation->forceFill([
            'last_message_at' => $message->created_at,
            'last_message_preview' => Str::limit($body, 160),
        ])->save();

        // Push the system line into the chat stream so open transcripts
        // append the row in real time. Sender is the seller (the actor
        // here); the recipient channel target is the buyer.
        $conversation->loadMissing(['buyer']);
        $buyer = $conversation->buyer;
        $message->load('sender');

        DB::afterCommit(function () use ($message, $conversation, $buyer): void {
            MessageSent::dispatch($message, $conversation->fresh() ?? $conversation, $buyer);
        });
    }
}
