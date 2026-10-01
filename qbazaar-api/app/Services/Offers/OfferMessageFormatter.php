<?php

declare(strict_types=1);

namespace App\Services\Offers;

use App\Data\Messaging\ChatMessageDraft;
use App\Enums\ChatMessageKey;

/**
 * Chat bubble that carries an offer. Clients render the offer card from the
 * structured `message.offer` payload; the bubble text only shows in the
 * inbox preview and in clients that predate offer cards.
 */
class OfferMessageFormatter
{
    public function bubble(ChatMessageKey $key, float $amount, ?string $note): ChatMessageDraft
    {
        return ChatMessageDraft::offer(
            $key,
            number_format($amount, 2, '.', ''),
            (string) config('qbazaar.default_currency', 'QAR'),
            $note,
        );
    }
}
