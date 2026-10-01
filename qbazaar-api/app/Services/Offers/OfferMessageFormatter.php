<?php

declare(strict_types=1);

namespace App\Services\Offers;

/**
 * Plain-text body of an offer chat bubble. Clients render the offer card
 * from the structured `message.offer` payload; this text only shows in the
 * inbox preview and in clients that predate offer cards.
 */
class OfferMessageFormatter
{
    public function body(float $amount, ?string $note): string
    {
        $base = sprintf(
            'Offer: %s %s',
            number_format($amount, 2, '.', ''),
            config('qbazaar.default_currency', 'QAR'),
        );

        return $note !== null && $note !== '' ? $base . ' — ' . $note : $base;
    }
}
