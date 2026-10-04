<?php

declare(strict_types=1);

namespace App\Data\Orders;

use App\Enums\Fulfillment;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\UserAddress;
use Illuminate\Support\Collection;

/**
 * Everything the checkout screen needs, read in one go.
 */
final readonly class CheckoutSummary
{
    /**
     * @param list<Fulfillment> $fulfillmentOptions
     * @param list<PaymentMethod> $paymentMethods
     * @param Collection<int, UserAddress> $savedAddresses
     * @param array<string, CheckoutQuote> $quotes keyed by fulfillment value
     */
    public function __construct(
        public Order $order,
        public array $fulfillmentOptions,
        public ?string $deliveryFee,
        public int $maxQuantity,
        public array $paymentMethods,
        public Collection $savedAddresses,
        public array $quotes,
    ) {}
}
