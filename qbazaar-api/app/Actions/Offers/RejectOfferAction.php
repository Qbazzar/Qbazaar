<?php

declare(strict_types=1);

namespace App\Actions\Offers;

use App\Enums\MessageType;
use App\Enums\OfferStatus;
use App\Events\Messaging\MessageSent;
use App\Events\Offers\OfferRejected;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seller rejects a pending offer. Same shape as {@see AcceptOfferAction}
 * minus the sibling-withdraw step (a rejection doesn't imply any other
 * offers should change state).
 *
 * Guards:
 *  - OFFER_FORBIDDEN
 *  - OFFER_NOT_PENDING
 */
class RejectOfferAction
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
        $body = 'Offer rejected';

        DB::transaction(function () use ($offer, $conversation, $body): void {
            $offer->forceFill([
                'status' => OfferStatus::REJECTED->value,
                'rejected_at' => now(),
            ])->save();

            $this->appendSystemBubble($conversation, $body);
        });

        $offer->refresh();
        $buyer = $offer->buyer;

        DB::afterCommit(function () use ($offer, $buyer): void {
            OfferRejected::dispatch($offer, $buyer->id);
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

        $conversation->loadMissing(['buyer']);
        $buyer = $conversation->buyer;
        $message->load('sender');

        DB::afterCommit(function () use ($message, $conversation, $buyer): void {
            MessageSent::dispatch($message, $conversation->fresh() ?? $conversation, $buyer);
        });
    }
}
