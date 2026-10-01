<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CHUNK = 500;

    /** English bodies written before bubbles were keyed. */
    private const SYSTEM_BODIES = [
        'Offer accepted' => 'offer.accepted',
        'Offer rejected' => 'offer.rejected',
        'Offer withdrawn' => 'offer.withdrawn',
    ];

    private const LEGACY_OFFER_BODY = '/^Offer: (?<amount>\d+(?:\.\d+)?) (?<currency>[A-Z]{3})(?: — (?<note>.*))?$/su';

    public function up(): void
    {
        foreach (self::SYSTEM_BODIES as $body => $key) {
            DB::table('messages')
                ->where('type', 'system')
                ->whereNull('message_key')
                ->where('body', $body)
                ->update(['message_key' => $key]);
        }

        $this->backfillOfferBubbles();
        $this->backfillConversationPreviews();
    }

    /**
     * Keys stay: `body` still holds the English text, so older code reads
     * the rows unchanged.
     */
    public function down(): void {}

    /**
     * The offer row is the source of truth for the amount; the old body is
     * only parsed when the offer is gone.
     */
    private function backfillOfferBubbles(): void
    {
        DB::table('messages')
            ->select(['id', 'body'])
            ->where('type', 'offer')
            ->whereNull('message_key')
            ->chunkById(self::CHUNK, function (Collection $messages): void {
                $offers = DB::table('offers')
                    ->whereIn('message_id', $messages->pluck('id'))
                    ->get(['message_id', 'parent_offer_id', 'amount', 'currency', 'note'])
                    ->keyBy('message_id');

                foreach ($messages as $message) {
                    $offer = $offers->get($message->id);
                    $bubble = $offer !== null ? $this->fromOffer($offer) : $this->fromLegacyBody((string) $message->body);

                    if ($bubble === null) {
                        continue;
                    }

                    DB::table('messages')->where('id', $message->id)->update([
                        'message_key' => $bubble['key'],
                        'params' => json_encode($bubble['params'], JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
    }

    /**
     * @return array{key: string, params: array<string, string>}
     */
    private function fromOffer(object $offer): array
    {
        $params = [
            'amount' => number_format((float) $offer->amount, 2, '.', ''),
            'currency' => (string) $offer->currency,
        ];

        if (is_string($offer->note) && $offer->note !== '') {
            $params['note'] = $offer->note;
        }

        return [
            'key' => $offer->parent_offer_id === null ? 'offer.made' : 'offer.countered',
            'params' => $params,
        ];
    }

    /**
     * @return array{key: string, params: array<string, string>}|null
     */
    private function fromLegacyBody(string $body): ?array
    {
        if (preg_match(self::LEGACY_OFFER_BODY, $body, $matches) !== 1) {
            return null;
        }

        $params = ['amount' => $matches['amount'], 'currency' => $matches['currency']];

        if (($matches['note'] ?? '') !== '') {
            $params['note'] = $matches['note'];
        }

        return ['key' => 'offer.made', 'params' => $params];
    }

    /**
     * Only conversations whose preview came from a bubble need a key; their
     * latest message already carries it after the steps above.
     */
    private function backfillConversationPreviews(): void
    {
        DB::table('conversations')
            ->select(['id'])
            ->whereNull('last_message_key')
            ->where(function ($query): void {
                $query->whereIn('last_message_preview', array_keys(self::SYSTEM_BODIES))
                    ->orWhere('last_message_preview', 'like', 'Offer: %');
            })
            ->chunkById(self::CHUNK, function (Collection $conversations): void {
                foreach ($conversations as $conversation) {
                    $latest = DB::table('messages')
                        ->where('conversation_id', $conversation->id)
                        ->orderByDesc('created_at')
                        ->orderByDesc('id')
                        ->first(['message_key', 'params']);

                    if ($latest === null || $latest->message_key === null) {
                        continue;
                    }

                    DB::table('conversations')->where('id', $conversation->id)->update([
                        'last_message_key' => $latest->message_key,
                        'last_message_params' => $latest->params,
                    ]);
                }
            });
    }
};
