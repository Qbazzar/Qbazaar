<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Data\Orders\CheckoutQuote;
use App\Enums\AdShipping;
use App\Enums\Fulfillment;
use App\Enums\OrderSource;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Order;
use App\Support\Money;

/**
 * Checkout amounts, computed only here. The unit price and the commission
 * rate stay as frozen on the order; the shipping fee is the seller's fee on
 * the ad, frozen on the order when the checkout completes.
 */
class CheckoutCalculator
{
    public function __construct(private readonly CommissionRates $rates) {}

    public function quote(Order $order, ?Ad $ad, Fulfillment $fulfillment, int $quantity): CheckoutQuote
    {
        $maxQuantity = $this->maxQuantity($order, $ad);

        if ($quantity < 1 || $quantity > $maxQuantity) {
            throw new DomainException(ErrorCode::PURCHASE_QUANTITY_UNAVAILABLE, details: ['available' => $maxQuantity]);
        }

        $itemsSubtotal = Money::multiply($order->unit_price, $quantity);
        $shippingFee = $fulfillment === Fulfillment::DELIVERY ? $this->deliveryFee($ad) : Money::ZERO;

        return new CheckoutQuote(
            unitPrice: $order->unit_price,
            quantity: $quantity,
            itemsSubtotal: $itemsSubtotal,
            shippingFee: $shippingFee,
            total: Money::add($itemsSubtotal, $shippingFee),
            commission: $this->rates->commissionOn($itemsSubtotal, $order->commission_rate),
        );
    }

    /**
     * @return list<Fulfillment>
     */
    public function fulfillmentOptions(?Ad $ad): array
    {
        return $this->offersDelivery($ad) ? [Fulfillment::PICKUP, Fulfillment::DELIVERY] : [Fulfillment::PICKUP];
    }

    /**
     * A negotiated offer is a deal for what was agreed, so only an order
     * from a "Buy Now" request may change its quantity, within the ad's units.
     */
    public function maxQuantity(Order $order, ?Ad $ad): int
    {
        if ($order->source !== OrderSource::PURCHASE_REQUEST || $ad === null || $ad->trashed()) {
            return $order->quantity;
        }

        return max($order->quantity, $ad->quantity);
    }

    private function deliveryFee(?Ad $ad): string
    {
        if ($ad === null || ! $this->offersDelivery($ad)) {
            throw new DomainException(ErrorCode::CHECKOUT_DELIVERY_UNAVAILABLE);
        }

        return Money::of($ad->shipping_fee ?? Money::ZERO);
    }

    private function offersDelivery(?Ad $ad): bool
    {
        return $ad !== null && ! $ad->trashed() && $ad->shipping === AdShipping::DELIVERY;
    }
}
