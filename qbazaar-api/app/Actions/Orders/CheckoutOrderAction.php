<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Data\Orders\CheckoutDetails;
use App\Enums\Fulfillment;
use App\Enums\OrderStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Order;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\Orders\CheckoutCalculator;
use App\Services\Orders\OrderTransitionService;
use Illuminate\Support\Facades\DB;

/**
 * The buyer completes the checkout of a `created` order: how it reaches
 * them, where, how many and how they pay. The amounts are recomputed on the
 * server and saved in the same transaction that moves the order to
 * `awaiting_handover`, under the ad lock and then the order lock (the
 * documented ad → order order). Checking out an order that already awaits
 * the handover returns it unchanged, so a retried request is harmless.
 */
class CheckoutOrderAction
{
    private const array ADDRESS_FIELDS = ['full_name', 'phone', 'street', 'house_number', 'supplement', 'city', 'postal_code', 'location_id'];

    public function __construct(
        private readonly CheckoutCalculator $calculator,
        private readonly OrderTransitionService $transitions,
    ) {}

    public function __invoke(User $buyer, Order $order, CheckoutDetails $details): Order
    {
        if ($buyer->id !== $order->buyer_id) {
            throw new DomainException(ErrorCode::ORDER_FORBIDDEN);
        }

        $deliveryAddress = $details->fulfillment === Fulfillment::DELIVERY ? $this->deliveryAddress($buyer, $details) : null;

        return DB::transaction(function () use ($order, $details, $deliveryAddress): Order {
            $ad = $order->ad_id === null ? null : Ad::withTrashed()->whereKey($order->ad_id)->lockForUpdate()->first();

            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === OrderStatus::AWAITING_HANDOVER) {
                return $locked;
            }

            if ($locked->status !== OrderStatus::CREATED) {
                throw new DomainException(ErrorCode::ORDER_INVALID_TRANSITION, details: [
                    'from' => $locked->status->value,
                    'to' => OrderStatus::AWAITING_HANDOVER->value,
                ]);
            }

            $quote = $this->calculator->quote($locked, $ad, $details->fulfillment, $details->quantity ?? $locked->quantity);

            $locked->forceFill([
                'quantity' => $quote->quantity,
                'shipping_fee' => $quote->shippingFee,
                'total' => $quote->total,
                'commission_amount' => $quote->commission,
                'fulfillment' => $details->fulfillment,
                'delivery_address' => $deliveryAddress,
            ])->save();

            return $this->transitions->awaitHandover($locked, $details->paymentMethod);
        });
    }

    /**
     * A snapshot, so a later edit of the address book never changes the order.
     *
     * @return array<string, string|null>
     */
    private function deliveryAddress(User $buyer, CheckoutDetails $details): array
    {
        $source = $details->inlineAddress;

        if ($details->savedAddressId !== null) {
            $saved = UserAddress::query()->ownedBy($buyer)->whereKey($details->savedAddressId)->first()
                ?? throw new DomainException(ErrorCode::ADDRESS_NOT_FOUND);
            $source = $saved->only(self::ADDRESS_FIELDS);
        }

        $address = [];

        foreach (self::ADDRESS_FIELDS as $field) {
            $value = $source[$field] ?? null;
            $address[$field] = $value === null ? null : (string) $value;
        }

        return $address;
    }
}
