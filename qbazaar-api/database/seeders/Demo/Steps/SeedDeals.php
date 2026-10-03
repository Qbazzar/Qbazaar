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
use App\Actions\Orders\CancelOrderAction;
use App\Actions\Orders\ConfirmOrderHandoverAction;
use App\Actions\Reviews\CreateReviewAction;
use App\Actions\Users\BlockUserAction;
use App\Data\Messaging\ChatMessageDraft;
use App\Enums\AdStatus;
use App\Enums\OrderSource;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\Order;
use App\Models\User;
use App\Services\Ledger\WalletService;
use App\Services\Offers\OfferTransitionService;
use App\Support\Money;
use Database\Seeders\Demo\Catalog\Listings;
use Database\Seeders\Demo\Catalog\Phrases;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\DemoMedia;
use Database\Seeders\Demo\UpcomingFeatures;
use Illuminate\Support\Carbon;

/**
 * Buyers talk to sellers, haggle and close deals, entirely through the
 * production actions: offers walk their state machine, accepted offers
 * become orders through OrderPlacementService, handovers charge commission
 * through the ledger recipes, and sellers who hit the debt ceiling settle
 * before they can take the next order.
 */
final class SeedDeals
{
    private const string CHAT_ONLY = 'chat_only';

    private const string OFFER_PENDING = 'offer_pending';

    private const string OFFER_REJECTED = 'offer_rejected';

    private const string OFFER_WITHDRAWN = 'offer_withdrawn';

    private const string OFFER_EXPIRED = 'offer_expired';

    private const string OFFER_COUNTERED = 'offer_countered';

    private const string ORDER_CREATED = 'order_created';

    private const string ORDER_AWAITING_HANDOVER = 'order_awaiting_handover';

    private const string ORDER_COMPLETED = 'order_completed';

    private const string ORDER_CANCELLED = 'order_cancelled';

    private const string ORDER_DISPUTED = 'order_disputed';

    private const string COUNTER_ACCEPTED = 'counter_accepted';

    /** Every outcome appears within the first pass, so small runs still cover them all. */
    private const array SCENARIOS = [
        self::ORDER_COMPLETED, self::OFFER_PENDING, self::ORDER_CREATED, self::OFFER_COUNTERED,
        self::ORDER_AWAITING_HANDOVER, self::OFFER_REJECTED, self::ORDER_CANCELLED, self::COUNTER_ACCEPTED,
        self::OFFER_WITHDRAWN, self::ORDER_DISPUTED, self::OFFER_EXPIRED, self::CHAT_ONLY,
        self::ORDER_COMPLETED, self::OFFER_PENDING, self::CHAT_ONLY, self::ORDER_COMPLETED,
    ];

    private const array ORDER_SCENARIOS = [
        self::ORDER_CREATED, self::ORDER_AWAITING_HANDOVER, self::ORDER_COMPLETED,
        self::ORDER_CANCELLED, self::ORDER_DISPUTED, self::COUNTER_ACCEPTED,
    ];

    private const float CONVERSATIONS_PER_AD = 0.3;

    private const int OFFER_LIFETIME_DAYS = 7;

    private const int BLOCKED_PAIRS = 3;

    private const float HAGGLE_SHARE = 0.25;

    private const int PAIR_ATTEMPTS = 10;

    /** @var list<Ad> listings set aside for deals, each used once */
    private array $dealAds = [];

    /** @var list<Ad> listings shared by chats and offers that never close */
    private array $haggleAds = [];

    /** @var array<string, true> "adId:buyerId" pairs that already talk */
    private array $threads = [];

    /** @var list<Conversation> */
    private array $chatOnly = [];

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
        private readonly ConfirmOrderHandoverAction $confirmHandover,
        private readonly CancelOrderAction $cancelOrder,
        private readonly CreateReviewAction $createReview,
        private readonly BlockUserAction $blockUser,
        private readonly WalletService $wallets,
        private readonly UpcomingFeatures $upcoming,
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
        $this->settleSomeDebts($context);
    }

    private function playScenario(DemoContext $context, string $scenario): void
    {
        $pair = in_array($scenario, self::ORDER_SCENARIOS, true) ? $this->dealPair($context) : $this->hagglePair($context);

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
            default => $this->negotiate($context, $scenario, $conversation, $buyer, $ad, $moment),
        };

        $this->markRead($context, $conversation, $buyer, $ad->user);
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
                $photo = $this->media->chatPhoto($this->listings->colorFor($ad->category?->parent?->slug), 'Same as this one?');
                $context->clock->at($moment, fn () => $this->sendMessage->execute($buyer, $conversation, ChatMessageDraft::image($photo, $language === 'ar' ? 'نفس هذا؟' : 'Same as this one?')));
            }
        }

        return $moment;
    }

    private function negotiate(DemoContext $context, string $scenario, Conversation $conversation, User $buyer, Ad $ad, Carbon $moment): void
    {
        $seller = $ad->user;
        $offerAt = $moment->copy()->addHour();
        $offer = $context->clock->at($offerAt, fn (): Offer => ($this->makeOffer)($buyer, $conversation, $this->offerAmount($context, $ad, 0.8, 0.95), $this->offerNote($context, $buyer)));
        $answerAt = $offerAt->copy()->addHours($context->random->int(1, 10));

        match ($scenario) {
            self::OFFER_PENDING => null,
            self::OFFER_REJECTED => $context->clock->at($answerAt, fn () => ($this->rejectOffer)($seller, $offer)),
            self::OFFER_WITHDRAWN => $context->clock->at($answerAt, fn () => ($this->withdrawOffer)($buyer, $offer)),
            self::OFFER_EXPIRED => $this->offers->expireIfDue($offer),
            self::OFFER_COUNTERED => $this->counter($context, $seller, $offer, $ad, $answerAt),
            self::COUNTER_ACCEPTED => $this->closeDeal($context, $scenario, $this->counter($context, $seller, $offer, $ad, $answerAt), $buyer, $ad, $answerAt->copy()->addHours(2)),
            default => $this->closeDeal($context, $scenario, $offer, $seller, $ad, $answerAt),
        };
    }

    private function counter(DemoContext $context, User $seller, Offer $offer, Ad $ad, Carbon $at): Offer
    {
        return $context->clock->at($at, fn (): Offer => ($this->counterOffer)($seller, $offer, $this->offerAmount($context, $ad, 0.9, 0.98), null));
    }

    /**
     * The responder accepts (placing the order and reserving the ad), then
     * the order is taken as far as the scenario asks.
     */
    private function closeDeal(DemoContext $context, string $scenario, Offer $offer, User $acceptedBy, Ad $ad, Carbon $at): void
    {
        $buyer = $offer->buyer;
        $this->ensureSellerCanTakeOrders($context, $ad->user, $at);

        $context->clock->at($at, fn () => ($this->acceptOffer)($acceptedBy, $offer));
        $order = Order::query()->where('source', OrderSource::OFFER->value)->where('source_id', $offer->id)->firstOrFail();

        if ($scenario === self::ORDER_CREATED) {
            return;
        }

        $handoverAt = $at->copy()->addDays($context->random->int(1, 3));
        $context->clock->at($at->copy()->addHour(), fn () => $this->upcoming->checkout($order));

        match ($scenario) {
            self::ORDER_COMPLETED, self::COUNTER_ACCEPTED => $this->complete($context, $order, $buyer, $ad, $handoverAt),
            self::ORDER_CANCELLED => $context->clock->at($handoverAt, fn () => ($this->cancelOrder)($buyer, $order, $context->random->pick(Phrases::CANCELLATION_REASONS[$buyer->language->value]))),
            self::ORDER_DISPUTED => $context->clock->at($handoverAt, fn () => $this->upcoming->openDispute($order)),
            default => null,
        };
    }

    private function complete(DemoContext $context, Order $order, User $buyer, Ad $ad, Carbon $handoverAt): void
    {
        $context->clock->at($handoverAt, fn () => ($this->confirmHandover)($ad->user, $order));

        if (! $context->random->chance(0.8)) {
            return;
        }

        $rating = $context->random->pick([5, 5, 5, 4, 4, 3, 2]);
        $context->clock->at(
            $handoverAt->copy()->addHours($context->random->int(2, 30)),
            fn () => $this->createReview->execute($buyer, $ad, $rating, Phrases::REVIEWS[$rating][$buyer->language->value]),
        );
    }

    /**
     * A seller at the commission debt ceiling cannot take new orders, so
     * they settle their debt by bank transfer first, as they would for real.
     */
    private function ensureSellerCanTakeOrders(DemoContext $context, User $seller, Carbon $at): void
    {
        if ($this->wallets->canAcceptOrders($seller->id)) {
            return;
        }

        $debt = $this->wallets->summary($seller->id)->commissionDebt;
        $context->clock->at($at->copy()->subHour(), fn () => $this->upcoming->settleCommissionByBankTransfer($seller, $debt, $context->superAdmin()));
    }

    /**
     * Leaves some commission owed, settles some by bank transfer and nets
     * some against a wallet credit, so every wallet state can be explored.
     */
    private function settleSomeDebts(DemoContext $context): void
    {
        $admin = $context->superAdmin();

        foreach ($context->members as $seller) {
            $debt = $this->wallets->summary($seller->id)->commissionDebt;

            if (! Money::isPositive($debt)) {
                continue;
            }

            match ($context->random->pick(['owed', 'owed', 'bank', 'netting'])) {
                'bank' => $this->upcoming->settleCommissionByBankTransfer($seller, $debt, $admin),
                'netting' => $this->netAgainstWallet($seller, $debt, $admin),
                default => null,
            };
        }
    }

    private function netAgainstWallet(User $seller, string $debt, User $admin): void
    {
        $this->upcoming->creditWallet($seller, Money::add($debt, '150.00'), 'Goodwill credit after a delivery issue', $admin);
        $this->upcoming->settleCommissionFromWallet($seller, $debt, $admin);
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
     *
     * @return array{Ad, User}|null
     */
    private function dealPair(DemoContext $context): ?array
    {
        $ad = array_pop($this->dealAds);

        return $ad === null ? null : [$ad, $context->memberOtherThan($ad->user_id)];
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

    private function offerAmount(DemoContext $context, Ad $ad, float $minShare, float $maxShare): float
    {
        $share = $context->random->int((int) ($minShare * 100), (int) ($maxShare * 100)) / 100;
        $amount = (float) $ad->price * $share;
        $step = $amount >= 1_000 ? 50 : 5;

        return max(1, round($amount / $step) * $step);
    }

    private function offerNote(DemoContext $context, User $buyer): ?string
    {
        return $context->random->chance(0.5) ? $context->random->pick(Phrases::OFFER_NOTES[$buyer->language->value]) : null;
    }
}
