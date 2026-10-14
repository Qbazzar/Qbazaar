<?php

declare(strict_types=1);

namespace App\Services\Admin\Ads;

use App\Data\Admin\AdDeal\AdTransaction;
use App\Data\Admin\AdDeal\BuyerBasis;
use App\Data\Admin\AdDeal\DealAccess;
use App\Data\Admin\AdDeal\OrderDeal;
use App\Enums\AdStatus;
use App\Enums\OfferStatus;
use App\Models\Ad;
use App\Models\Offer;
use App\Models\Order;
use App\Models\User;
use App\Services\Admin\TimelineEntry;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * The transaction behind an ad for the admin ad page: who bought, the order
 * and its money, the chats and the offers. Each part is read only when the
 * viewer holds the permission that guards that data (finance.view for orders
 * and the ledger, offers.view, conversations.view, users.view), so a role
 * without it costs no query and sees no link. Lists are bounded, totals are
 * single counts, and everything a row shows is eager-loaded.
 */
final class AdTransactionSummary
{
    public const int LIST_LIMIT = 5;

    private const int ORDER_LOOKBACK = 5;

    public function __construct(
        private readonly OrderDealBuilder $deals,
        private readonly AdChatPanel $chats,
    ) {}

    /**
     * @param Collection<int, TimelineEntry>|null $statusMoves the ad's status history, merged into the timeline
     */
    public function forAd(Ad $ad, User $viewer, ?Collection $statusMoves = null): AdTransaction
    {
        $access = DealAccess::of($viewer);
        $orders = $access->finance ? $this->ordersOf($ad) : new EloquentCollection;
        $order = $this->currentOrder($orders);
        $dealing = $ad->status === AdStatus::SOLD || $ad->isReserved();
        $acceptedOffer = $dealing ? $this->acceptedOffer($ad, $access, $order) : null;
        [$buyer, $basis] = $this->buyerOf($ad, $access, $order, $acceptedOffer);

        return new AdTransaction(
            access: $access,
            showDeal: $order !== null || $dealing,
            buyer: $access->users ? $buyer : null,
            buyerBasis: $basis,
            deal: $order === null ? null : $this->dealOf($order, $statusMoves, $orders->count() - 1),
            conversations: $access->conversations ? $this->chats->conversations($ad) : new Collection,
            conversationsTotal: (int) ($ad->conversations_count ?? 0),
            offers: $access->offers ? $this->chats->offers($ad, self::LIST_LIMIT) : new EloquentCollection,
            offersTotal: $access->offers ? $this->chats->offerCount($ad) : 0,
        );
    }

    /**
     * Newest first, so the live order (one open order per ad) or the latest
     * outcome is found without a second query. Served by orders_ad_status_idx.
     *
     * @return EloquentCollection<int, Order>
     */
    private function ordersOf(Ad $ad): EloquentCollection
    {
        return Order::query()
            ->where('ad_id', $ad->id)
            ->with(['buyer', 'buyer.avatarMedia'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::ORDER_LOOKBACK)
            ->get();
    }

    /**
     * @param EloquentCollection<int, Order> $orders
     */
    private function currentOrder(EloquentCollection $orders): ?Order
    {
        return $orders->first(fn (Order $order): bool => $order->status->isActive())
            ?? $orders->first(fn (Order $order): bool => $order->completed_at !== null)
            ?? $orders->first();
    }

    /**
     * @param Collection<int, TimelineEntry>|null $statusMoves
     */
    private function dealOf(Order $order, ?Collection $statusMoves, int $otherOrders): OrderDeal
    {
        return $this->deals->build($order, $statusMoves, $otherOrders);
    }

    /**
     * The offer that closed (or reserved) the deal, read for a sold or reserved
     * ad when there is no order to name the buyer.
     */
    private function acceptedOffer(Ad $ad, DealAccess $access, ?Order $order): ?Offer
    {
        if (! $access->offers || $order !== null) {
            return null;
        }

        return Offer::query()
            ->where('ad_id', $ad->id)
            ->where('status', OfferStatus::ACCEPTED->value)
            ->with(['buyer', 'buyer.avatarMedia'])
            ->orderByDesc('accepted_at')
            ->first();
    }

    /**
     * @return array{0: User|null, 1: BuyerBasis}
     */
    private function buyerOf(Ad $ad, DealAccess $access, ?Order $order, ?Offer $offer): array
    {
        return match (true) {
            $order !== null => [$order->buyer, BuyerBasis::ORDER],
            $offer !== null => [$offer->buyer, BuyerBasis::ACCEPTED_OFFER],
            $access->finance && $ad->status === AdStatus::SOLD => [null, BuyerBasis::OFF_PLATFORM],
            default => [null, BuyerBasis::UNKNOWN],
        };
    }
}
