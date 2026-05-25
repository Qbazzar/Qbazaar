<?php

declare(strict_types=1);

namespace App\Actions\Offers;

use App\Enums\MessageType;
use App\Enums\OfferStatus;
use App\Events\Messaging\MessageSent;
use App\Events\Offers\OfferWithdrawn;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Buyer withdraws their own pending offer.
 *
 * Guards:
 *  - OFFER_FORBIDDEN  : caller is not the offer's buyer.
 *  - OFFER_NOT_PENDING: terminal or expired.
 */
class WithdrawOfferAction
{
    public function __invoke(User $buyer, Offer $offer): Offer
    {
        if ($buyer->id !== $offer->buyer_id) {
            throw new DomainException(ErrorCode::OFFER_FORBIDDEN);
        }

        if (! $offer->isActive()) {
            throw new DomainException(ErrorCode::OFFER_NOT_PENDING);
        }

        $offer->loadMissing('conversation');
        $conversation = $offer->conversation;
        $body = 'Offer withdrawn';

        DB::transaction(function () use ($offer, $conversation, $body): void {
            $offer->forceFill([
                'status' => OfferStatus::WITHDRAWN->value,
                'withdrawn_at' => now(),
            ])->save();

            $this->appendSystemBubble($conversation, $body);
        });

        $offer->refresh();
        $seller = $offer->seller;

        DB::afterCommit(function () use ($offer, $seller): void {
            OfferWithdrawn::dispatch($offer, $seller->id);
        });

        return $offer;
    }

    /**
     * Withdraw bubble is authored by the buyer (the actor) so the
     * sender_id matches who pressed the button. The system tag still
     * keeps the chat-bubble UI rendering it as a notice rather than a
     * normal chat line.
     */
    private function appendSystemBubble(Conversation $conversation, string $body): void
    {
        /** @var Message $message */
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $conversation->buyer_id,
            'body' => $body,
            'type' => MessageType::SYSTEM->value,
        ]);

        $conversation->forceFill([
            'last_message_at' => $message->created_at,
            'last_message_preview' => Str::limit($body, 160),
        ])->save();

        $conversation->loadMissing(['seller']);
        $seller = $conversation->seller;
        $message->load('sender');

        DB::afterCommit(function () use ($message, $conversation, $seller): void {
            MessageSent::dispatch($message, $conversation->fresh() ?? $conversation, $seller);
        });
    }
}
