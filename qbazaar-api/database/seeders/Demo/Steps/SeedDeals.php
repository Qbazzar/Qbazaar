<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Steps;

use App\Actions\Messaging\MarkConversationReadAction;
use App\Actions\Messaging\SendMessageAction;
use App\Actions\Messaging\StartConversationAction;
use App\Actions\Offers\AcceptOfferAction;
use App\Actions\Offers\CounterOfferAction;
use App\Actions\Offers\MakeOfferAction;
use App\Actions\Offers\RejectOfferAction;
use App\Actions\Offers\WithdrawOfferAction;
use App\Actions\Users\BlockUserAction;
use App\Data\Messaging\ChatMessageDraft;
use App\Enums\AdShipping;
use App\Enums\AdStatus;
use App\Enums\Fulfillment;
use App\Enums\OrderSource;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\Order;
use App\Models\User;
use App\Services\Offers\OfferTransitionService;
use App\Support\Money;
use Database\Seeders\Demo\Catalog\Listings;
use Database\Seeders\Demo\Catalog\Phrases;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\DemoFinance;
use Database\Seeders\Demo\DemoMedia;
use Database\Seeders\Demo\OrderJourney;
use Database\Seeders\Demo\OrderOutcome;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Buyers talk to sellers, haggle and close deals, entirely through the
 * production actions: offers walk their state machine, accepted offers
 * become orders through OrderPlacementService, and OrderJourney takes each
 * order through checkout, handover, cancellation or a dispute. Sellers who
 * hit the commission debt ceiling settle by bank transfer before they can
 * take the next order, as they would for real.
 */
final class SeedDeals
{
    private const string CHAT_ONLY = 'chat_only';

    private const string OFFER_PENDING = 'offer_pending';

    private const string OFFER_REJECTED = 'offer_rejected';

    private const string OFFER_WITHDRAWN = 'offer_withdrawn';

    private const string OFFER_EXPIRED = 'offer_expired';

    private const string OFFER_COUNTERED = 'offer_countered';

    private const string COUNTER_ACCEPTED = 'counter_accepted';

    /** Every outcome appears within the first pass, so small runs still cover them all. */
    private const array SCENARIOS = [
        OrderOutcome::COMPLETED->name, self::OFFER_PENDING, OrderOutcome::CREATED->name, self::OFFER_COUNTERED,
        OrderOutcome::AWAITING_HANDOVER->name, self::OFFER_REJECTED, OrderOutcome::CANCELLED->name, self::COUNTER_ACCEPTED,
        self::OFFER_WITHDRAWN, OrderOutcome::DISPUTE_OPEN->name, self::OFFER_EXPIRED, self::CHAT_ONLY,
        OrderOutcome::COMPLETED->name, self::OFFER_PENDING, OrderOutcome::DISPUTE_UPHELD->name, self::CHAT_ONLY,
        OrderOutcome::COMPLETED->name, OrderOutcome::DISPUTE_DISMISSED->name,
    ];

    private const float CONVERSATIONS_PER_AD = 0.3;

    private const int OFFER_LIFETIME_DAYS = 7;

    private const int BLOCKED_PAIRS = 3;

    private const float HAGGLE_SHARE = 0.25;

    private const int PAIR_ATTEMPTS = 10;

    private const string UPGRADED_DELIVERY_FEE = '25.00';

    /** @var list<Ad> listings set aside for deals, each used once */
    private array $dealAds = [];

    /** @var list<Ad> listings shared by chats and offers that never close */
    private array $haggleAds = [];

    /** @var array<string, true> "adId:buyerId" pairs that already talk */
    private array $threads = [];

    /** @var list<Conversation> */
    private array $chatOnly = [];

    private bool $deliveryTurn = false;

    public function __construct(
        private readonly StartConversationAction $startConversation,
        private readonly SendMessageAction $sendMessage,
        private readonly MarkConversationReadAction $markRead,
        private readonly MakeOfferAction $makeOffer,
        private readonly CounterOfferAction $counterOffer,
        private readonly AcceptOfferAction $acceptOffer,
        private readonly RejectOfferAction $rejectOffer,
        private readonly WithdrawOfferAction $withdrawOffer,
        private readonly OfferTransitionService $offers,
        private readonly OrderJourney $journey,
        private readonly BlockUserAction $blockUser,
        private readonly DemoFinance $finance,
        private readonly Listings $listings,
        private readonly DemoMedia $media,
    ) {}

    public function run(DemoContext $context): void
    {
        $liveAds = Ad::query()
            ->with(['user', 'category.parent'])
            ->where('status', AdStatus::ACTIVE->value)
            ->whereNotNull('price')
            ->get()
            ->all();

        if (count($liveAds) < 2) {
            return;
        }

        $haggleCount = max(1, (int) round(count($liveAds) * self::HAGGLE_SHARE));
        $this->haggleAds = array_slice($liveAds, 0, $haggleCount);
        $this->dealAds = array_slice($liveAds, $haggleCount);

        $conversations = max(count(self::SCENARIOS), (int) round($context->options->ads * self::CONVERSATIONS_PER_AD));

        for ($index = 0; $index < $conversations; $index++) {
            $this->playScenario($context, self::SCENARIOS[$index % count(self::SCENARIOS)]);
        }

        $this->blockSomePairs($context);
        $context->spareAds = $this->dealAds;
    }

    private function playScenario(DemoContext $context, string $scenario): void
    {
        $outcome = $this->orderOutcome($scenario);
        $pair = $outcome === null ? $this->hagglePair($context) : $this->dealPair($context);

        if ($pair === null) {
            return;
        }

        [$ad, $buyer] = $pair;
        $this->threads["{$ad->id}:{$buyer->id}"] = true;
        $startedAt = $context->clock->daysAgo($this->daysAgoFor($context, $scenario), $context->random->int(0, 600));

        $conversation = $context->clock->at($startedAt, fn (): Conversation => $this->startConversation->execute($buyer, $ad)['conversation']);
        $moment = $this->chat($context, $conversation, $buyer, $ad->user, $ad, $startedAt);

        match ($scenario) {
            self::CHAT_ONLY => $this->chatOnly[] = $conversation,
            default => $this->negotiate($context, $scenario, $outcome, $conversation, $buyer, $ad, $moment),
        };

        $this->markRead($context, $conversation, $buyer, $ad->user);
    }

    /**
     * The state the scenario's order ends in, or null when no order is placed.
     */
    private function orderOutcome(string $scenario): ?OrderOutcome
    {
        return match ($scenario) {
            self::COUNTER_ACCEPTED => OrderOutcome::COMPLETED,
            self::CHAT_ONLY, self::OFFER_PENDING, self::OFFER_REJECTED, self::OFFER_WITHDRAWN, self::OFFER_EXPIRED, self::OFFER_COUNTERED => null,
            default => constant(OrderOutcome::class . '::' . $scenario),
        };
    }

    /**
     * @return Carbon when the last message was sent
     */
    private function chat(DemoContext $context, Conversation $conversation, User $buyer, User $seller, Ad $ad, Carbon $moment): Carbon
    {
        $language = $buyer->language->value;
        $lines = $context->random->pick(Phrases::CHATS[$language]);

        foreach ($lines as $turn => $line) {
            $moment = $moment->copy()->addMinutes($context->random->int(2, 90));
            $author = $turn % 2 === 0 ? $buyer : $seller;
            $context->clock->at($moment, fn () => $this->sendMessage->execute($author, $conversation, ChatMessageDraft::text($line)));

            if ($turn === 0 && $context->random->chance(0.2)) {
                $photo = $this->media->chatPhoto($this->listings->colorFor($ad->category->parent?->slug), 'Same as this one?');
                $context->clock->at($moment, fn () => $this->sendMessage->execute($buyer, $conversation, ChatMessageDraft::image($photo, $language === 'ar' ? 'نفس هذا؟' : 'Same as this one?')));
            }
        }

        return $moment;
    }

    private function negotiate(DemoContext $context, string $scenario, ?OrderOutcome $outcome, Conversation $conversation, User $buyer, Ad $ad, Carbon $moment): void
    {
        $seller = $ad->user;
        $offerAt = $moment->copy()->addHour();
        $offer = $context->clock->at($offerAt, fn (): Offer => ($this->makeOffer)($buyer, $conversation, $this->offerAmount($context, $ad, 80, 95), $this->offerNote($context, $buyer)));
        $answerAt = $offerAt->copy()->addHours($context->random->int(1, 10));
        $placesOrder = fn (): OrderOutcome => $outcome ?? throw new LogicException("Scenario {$scenario} places no order.");

        match ($scenario) {
            self::OFFER_PENDING => null,
            self::OFFER_REJECTED => $context->clock->at($answerAt, fn () => ($this->rejectOffer)($seller, $offer)),
            self::OFFER_WITHDRAWN => $context->clock->at($answerAt, fn () => ($this->withdrawOffer)($buyer, $offer)),
            self::OFFER_EXPIRED => $this->offers->expireIfDue($offer),
            self::OFFER_COUNTERED => $this->counter($context, $seller, $offer, $ad, $answerAt),
            self::COUNTER_ACCEPTED => $this->closeDeal($context, $placesOrder(), $this->counter($context, $seller, $offer, $ad, $answerAt), $buyer, $ad, $answerAt->copy()->addHours(2)),
            default => $this->closeDeal($context, $placesOrder(), $offer, $seller, $ad, $answerAt),
        };
    }

    private function counter(DemoContext $context, User $seller, Offer $offer, Ad $ad, Carbon $at): Offer
    {
        return $context->clock->at($at, fn (): Offer => ($this->counterOffer)($seller, $offer, $this->offerAmount($context, $ad, 90, 98), null));
    }

    /**
     * The responder accepts (placing the order and reserving the ad), then
     * the order is taken as far as the scenario asks.
     */
    private function closeDeal(DemoContext $context, OrderOutcome $outcome, Offer $offer, User $acceptedBy, Ad $ad, Carbon $at): void
    {
        $this->finance->ensureCanTakeOrders($context, $ad->user, $at);

        $context->clock->at($at, fn () => ($this->acceptOffer)($acceptedBy, $offer));
        $order = Order::query()->where('source', OrderSource::OFFER->value)->where('source_id', $offer->id)->firstOrFail();
        $fulfillment = $ad->shipping === AdShipping::DELIVERY && $this->deliveryTurn ? Fulfillment::DELIVERY : Fulfillment::PICKUP;

        $this->journey->run($context, $order, $ad, $offer->buyer, $outcome, $at, $fulfillment);
    }

    private function blockSomePairs(DemoContext $context): void
    {
        foreach ($context->random->sample($this->chatOnly, self::BLOCKED_PAIRS) as $conversation) {
            $conversation->loadMissing(['buyer', 'seller']);
            $this->blockUser->execute($conversation->seller, $conversation->buyer);
        }
    }

    private function markRead(DemoContext $context, Conversation $conversation, User $buyer, User $seller): void
    {
        $this->markRead->execute($buyer, $conversation);

        if ($context->random->chance(0.65)) {
            $this->markRead->execute($seller, $conversation);
        }
    }

    /**
     * Open offers stay inside their lifetime and the expired one is well past it.
     */
    private function daysAgoFor(DemoContext $context, string $scenario): int
    {
        return match ($scenario) {
            self::OFFER_EXPIRED => $context->random->int(self::OFFER_LIFETIME_DAYS + 3, self::OFFER_LIFETIME_DAYS + 8),
            self::OFFER_PENDING, self::OFFER_COUNTERED => $context->random->int(1, 4),
            default => $context->random->int(2, 20),
        };
    }

    /**
     * A deal reserves its listing, so each one gets a listing of its own.
     * Deals alternate between pickup and delivery; a listing that only
     * offers pickup is switched to delivery for the delivery turns.
     *
     * @return array{Ad, User}|null
     */
    private function dealPair(DemoContext $context): ?array
    {
        $ad = array_pop($this->dealAds);

        if ($ad === null) {
            return null;
        }

        $this->deliveryTurn = ! $this->deliveryTurn;

        if ($this->deliveryTurn && $ad->shipping !== AdShipping::DELIVERY) {
            $ad->forceFill(['shipping' => AdShipping::DELIVERY, 'shipping_fee' => self::UPGRADED_DELIVERY_FEE])->saveQuietly();
        }

        return [$ad, $context->memberOtherThan($ad->user_id)];
    }

    /**
     * Chats and offers that never close share listings, one thread per
     * buyer and listing.
     *
     * @return array{Ad, User}|null
     */
    private function hagglePair(DemoContext $context): ?array
    {
        for ($attempt = 0; $attempt < self::PAIR_ATTEMPTS; $attempt++) {
            $ad = $context->random->pick($this->haggleAds);
            $buyer = $context->memberOtherThan($ad->user_id);

            if (! isset($this->threads["{$ad->id}:{$buyer->id}"])) {
                return [$ad, $buyer];
            }
        }

        return null;
    }

    /**
     * A round figure a little under the asking price, worked out in whole
     * riyals; the offer actions take the amount as a number.
     */
    private function offerAmount(DemoContext $context, Ad $ad, int $minPercent, int $maxPercent): float
    {
        $wholePrice = (int) Money::of((string) $ad->price);
        $amount = intdiv($wholePrice * $context->random->int($minPercent, $maxPercent), 100);
        $step = $amount >= 1_000 ? 50 : 5;

        return (float) max(1, intdiv($amount, $step) * $step);
    }

    private function offerNote(DemoContext $context, User $buyer): ?string
    {
        return $context->random->chance(0.5) ? $context->random->pick(Phrases::OFFER_NOTES[$buyer->language->value]) : null;
    }
}
