<?php

declare(strict_types=1);

namespace App\Actions\Offers;

use App\Enums\AdStatus;
use App\Enums\MessageType;
use App\Enums\OfferStatus;
use App\Events\Messaging\MessageSent;
use App\Events\Offers\OfferCreated;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a buyer-side offer attached to a conversation.
 *
 * Sequence inside the transaction:
 *   1. Insert the chat bubble (`type=offer`) so the offer message has a
 *      stable id we can pin on the Offer row.
 *   2. Insert the Offer row referencing that message via `message_id`.
 *   3. Refresh the conversation preview so the inbox lists the new
 *      activity without a sub-query against `messages`.
 *
 * All three writes happen in a single DB::transaction. The `MessageSent`
 * + `OfferCreated` broadcasts are deferred to `afterCommit` so Reverb
 * subscribers can't observe rows that don't exist yet — same pattern as
 * SendMessageAction.
 *
 * Domain invariants enforced (each throws a stable ErrorCode):
 *   - OFFER_OWN_AD          : buyer can't offer on their own ad.
 *   - OFFER_AD_NOT_ACTIVE   : refuses non-ACTIVE ads (draft/sold/expired/...).
 *   - MSG_BLOCKED           : same block-check used by messaging — refuse
 *                             when either side has blocked the other.
 *   - OFFER_ACTIVE_EXISTS   : one open offer per (buyer, ad).
 */
class MakeOfferAction
{
    public function __invoke(
        User $buyer,
        Conversation $conversation,
        float $amount,
        ?string $note,
    ): Offer {
        $conversation->loadMissing(['ad', 'buyer', 'seller']);

        $ad = $conversation->ad;
        $seller = $conversation->seller;

        // Cheapest checks first — own-ad + ad-status don't touch the
        // blocked-users join.
        if ($buyer->id === $conversation->seller_id) {
            throw new DomainException(ErrorCode::OFFER_OWN_AD);
        }

        if ($ad->status !== AdStatus::ACTIVE) {
            throw new DomainException(ErrorCode::OFFER_AD_NOT_ACTIVE);
        }

        if ($buyer->hasBlocked($seller) || $seller->hasBlocked($buyer)) {
            throw new DomainException(ErrorCode::MSG_BLOCKED);
        }

        $activeExists = Offer::query()
            ->where('buyer_id', $buyer->id)
            ->where('ad_id', $conversation->ad_id)
            ->where('status', OfferStatus::PENDING->value)
            ->exists();

        if ($activeExists) {
            throw new DomainException(ErrorCode::OFFER_ACTIVE_EXISTS);
        }

        $expiryDays = (int) config('qbazaar.offers.expiry_days', 7);
        $body = $this->formatBody($amount, $note);

        /** @var array{message: Message, offer: Offer} $result */
        $result = DB::transaction(function () use (
            $buyer,
            $conversation,
            $amount,
            $note,
            $body,
            $expiryDays,
        ): array {
            /** @var Message $message */
            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $buyer->id,
                'body' => $body,
                'type' => MessageType::OFFER->value,
            ]);

            /** @var Offer $offer */
            $offer = Offer::query()->create([
                'conversation_id' => $conversation->id,
                'ad_id' => $conversation->ad_id,
                'buyer_id' => $buyer->id,
                'seller_id' => $conversation->seller_id,
                'message_id' => $message->id,
                'amount' => $amount,
                'currency' => config('qbazaar.default_currency', 'QAR'),
                'note' => $note,
                'status' => OfferStatus::PENDING->value,
                'expires_at' => now()->addDays($expiryDays),
            ]);

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                'last_message_preview' => Str::limit($body, 160),
            ])->save();

            return ['message' => $message, 'offer' => $offer];
        });

        $message = $result['message'];
        $offer = $result['offer'];

        // Hydrate relations the listeners + serialisers need so the
        // broadcasts don't trigger fresh queries inside the worker.
        $message->load('sender');
        $message->setRelation('offer', $offer);

        $fresh = $conversation->fresh() ?? $conversation;

        DB::afterCommit(function () use ($message, $offer, $fresh, $seller): void {
            // Reuse the messaging broadcast so existing chat subscribers
            // see the offer bubble appear inline without any FE changes.
            MessageSent::dispatch($message, $fresh, $seller);
            OfferCreated::dispatch($offer, $seller->id);
        });

        return $offer;
    }

    /**
     * Inline "I offer X.XX QAR — {note}" string used both as the chat
     * bubble body and as the conversation preview. Format is bilingual-
     * friendly: the FE re-renders offer cards from the structured
     * `message.offer` payload, so this text is only seen in
     * (a) legacy clients and (b) the inbox preview cell.
     */
    private function formatBody(float $amount, ?string $note): string
    {
        $currency = config('qbazaar.default_currency', 'QAR');
        $formatted = number_format($amount, 2, '.', '');

        $base = sprintf('Offer: %s %s', $formatted, $currency);

        if ($note !== null && $note !== '') {
            return $base . ' — ' . $note;
        }

        return $base;
    }
}
