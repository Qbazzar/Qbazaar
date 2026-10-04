<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Data\Orders\CheckoutSummary;
use App\Enums\Fulfillment;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Order;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\Orders\CheckoutCalculator;
use App\Services\Payments\PaymentGateways;

/**
 * The checkout screen for the buyer: the ways the item can reach them, the
 * seller's delivery fee, their saved addresses, the enabled payment methods
 * and the server-computed totals for each way at the current quantity.
 */
class ShowCheckoutAction
{
    public function __construct(
        private readonly CheckoutCalculator $calculator,
        private readonly PaymentGateways $gateways,
    ) {}

    public function __invoke(User $buyer, Order $order): CheckoutSummary
    {
        if ($buyer->id !== $order->buyer_id) {
            throw new DomainException(ErrorCode::ORDER_FORBIDDEN);
        }

        $ad = $order->ad_id === null ? null : Ad::withTrashed()->find($order->ad_id);
        $options = $this->calculator->fulfillmentOptions($ad);

        $quotes = [];

        foreach ($options as $fulfillment) {
            $quotes[$fulfillment->value] = $this->calculator->quote($order, $ad, $fulfillment, $order->quantity);
        }

        $deliveryQuote = $quotes[Fulfillment::DELIVERY->value] ?? null;

        return new CheckoutSummary(
            order: $order,
            fulfillmentOptions: $options,
            deliveryFee: $deliveryQuote?->shippingFee,
            maxQuantity: $this->calculator->maxQuantity($order, $ad),
            paymentMethods: $this->gateways->methods(),
            savedAddresses: UserAddress::query()->ownedBy($buyer)->orderByDesc('is_default')->latest()->get(),
            quotes: $quotes,
        );
    }
}
