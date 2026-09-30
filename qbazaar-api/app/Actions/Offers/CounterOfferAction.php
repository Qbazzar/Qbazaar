<?php

declare(strict_types=1);

namespace App\Actions\Offers;

use App\Enums\MessageType;
use App\Enums\OfferStatus;
use App\Enums\PlatformSetting;
use App\Events\Offers\OfferCountered;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Offer;
use App\Models\User;
use App\Services\Messaging\ConversationMessageWriter;
use App\Services\Offers\OfferMessageFormatter;
use App\Services\Offers\OfferTransitionService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;

/**
 * The responder answers an open offer with a different price.
 *
 * The answered offer becomes COUNTERED and a new open offer, proposed by
 * the responder, continues the negotiation one round further. Sides
 * alternate (the seller counters first), so capping the round number at
 * twice the per-side allowance caps each side's counters at that allowance.
 */
class CounterOfferAction
{
    public function __construct(
        private readonly OfferTransitionService $transitions,
        private readonly ConversationMessageWriter $messages,
        private readonly OfferMessageFormatter $formatter,
        private readonly SettingsService $settings,
    ) {}

    public function __invoke(User $actor, Offer $offer, float $amount, ?string $note): Offer
    {
        if ($actor->id !== $offer->responderId()) {
            throw new DomainException(ErrorCode::OFFER_FORBIDDEN);
        }

        return $this->transitions->withLockedOffer(
            $offer,
            fn (Offer $offer, Ad $ad): Offer => $this->counter($actor, $offer, $ad, $amount, $note),
        );
    }

    private function counter(User $actor, Offer $offer, Ad $ad, float $amount, ?string $note): Offer
    {
        $this->transitions->assertAdIsOpenForOffers($ad);

        $round = $offer->counter_round + 1;

        if ($round > 2 * $this->settings->integer(PlatformSetting::OFFER_COUNTER_ROUNDS_PER_SIDE)) {
            throw new DomainException(ErrorCode::OFFER_COUNTER_LIMIT_REACHED);
        }

        $this->transitions->moveTo($offer, OfferStatus::COUNTERED);

        $message = $this->messages->append(
            $offer->conversation,
            $actor,
            $this->formatter->body($amount, $note),
            MessageType::OFFER,
        );

        /** @var Offer $counter */
        $counter = Offer::query()->create([
            'conversation_id' => $offer->conversation_id,
            'ad_id' => $offer->ad_id,
            'buyer_id' => $offer->buyer_id,
            'seller_id' => $offer->seller_id,
            'proposed_by' => $offer->proposed_by->opposite(),
            'message_id' => $message->id,
            'parent_offer_id' => $offer->id,
            'counter_round' => $round,
            'amount' => $amount,
            'currency' => $offer->currency,
            'note' => $note,
            'status' => OfferStatus::PENDING,
            'expires_at' => now()->addDays((int) config('qbazaar.offers.expiry_days', 7)),
        ]);

        $message->setRelation('offer', $counter);

        DB::afterCommit(fn () => OfferCountered::dispatch($counter, $offer->proposerId()));

        return $counter;
    }
}
