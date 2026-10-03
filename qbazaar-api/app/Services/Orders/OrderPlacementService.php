<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Events\Orders\OrderCreated;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Offer;
use App\Models\Order;
use App\Services\Ads\AdLifecycleService;
use App\Services\Ledger\WalletService;
use App\Services\Payments\PaymentGateways;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Creates orders. The caller must hold the lock on the ad (the offer and
 * purchase-request flows already do), so the "one open order per ad" check
 * and the reservation cannot interleave with another order on the same ad;
 * the unique `active_ad_id` index backs that up at the database level.
 *
 * The price, the commission rate and the commission amount are computed
 * here, on the server, and frozen on the order.
 */
class OrderPlacementService
{
    public function __construct(
        private readonly CommissionRates $rates,
        private readonly WalletService $wallets,
        private readonly PaymentGateways $gateways,
        private readonly AdLifecycleService $ads,
    ) {}

    /**
     * Called by AcceptOfferAction inside its transaction, right after the
     * offer is accepted. Accepting the same offer again returns its order.
     */
    public function placeFromAcceptedOffer(Offer $offer, Ad $lockedAd): Order
    {
        return $this->place($lockedAd, $offer->buyer_id, OrderSource::OFFER, $offer->id, $offer->amount, 1);
    }

    /**
     * For the "Buy Now" purchase request flow (BE-14.34).
     */
    public function placeFromPurchaseRequest(string $purchaseRequestId, string $buyerId, Ad $lockedAd, string $unitPrice, int $quantity): Order
    {
        return $this->place($lockedAd, $buyerId, OrderSource::PURCHASE_REQUEST, $purchaseRequestId, $unitPrice, $quantity);
    }

    private function place(Ad $ad, string $buyerId, OrderSource $source, string $sourceId, string $unitPrice, int $quantity): Order
    {
        $existing = Order::query()->where('source', $source->value)->where('source_id', $sourceId)->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($ad, $buyerId, $source, $sourceId, $unitPrice, $quantity): Order {
            $this->assertCanPlace($ad);

            $order = $this->draft($ad, $buyerId, $source, $sourceId, $unitPrice, $quantity);

            try {
                $order->save();
            } catch (UniqueConstraintViolationException) {
                throw new DomainException(ErrorCode::ORDER_AD_HAS_ACTIVE_ORDER);
            }

            $this->ads->reserve($ad);

            DB::afterCommit(fn () => OrderCreated::dispatch($order));

            return $order;
        });
    }

    private function assertCanPlace(Ad $ad): void
    {
        if (! $this->wallets->canAcceptOrders($ad->user_id)) {
            throw new DomainException(ErrorCode::ORDER_SELLER_DEBT_CEILING);
        }

        if (Order::query()->where('ad_id', $ad->id)->active()->exists()) {
            throw new DomainException(ErrorCode::ORDER_AD_HAS_ACTIVE_ORDER);
        }
    }

    private function draft(Ad $ad, string $buyerId, OrderSource $source, string $sourceId, string $unitPrice, int $quantity): Order
    {
        $subtotal = Money::multiply(Money::of($unitPrice), $quantity);
        $rate = $this->rates->rateFor($ad->category_id);

        $order = new Order;
        $order->forceFill([
            'ad_id' => $ad->id,
            'buyer_id' => $buyerId,
            'seller_id' => $ad->user_id,
            'source' => $source,
            'source_id' => $sourceId,
            'status' => OrderStatus::CREATED,
            'ad_title' => $ad->title,
            'currency' => $ad->currency,
            'unit_price' => Money::of($unitPrice),
            'quantity' => $quantity,
            'shipping_fee' => Money::ZERO,
            'total' => $subtotal,
            'commission_rate' => $rate,
            'commission_amount' => $this->rates->commissionOn($subtotal, $rate),
            'payment_method' => $this->gateways->default()->method(),
        ]);

        return $order;
    }
}
