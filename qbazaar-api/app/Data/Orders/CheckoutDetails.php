<?php

declare(strict_types=1);

namespace App\Data\Orders;

use App\Enums\Fulfillment;
use App\Enums\PaymentMethod;

/**
 * What the buyer chose at checkout. No amount is ever taken from the
 * client: prices, the shipping fee and the totals are read on the server.
 */
final readonly class CheckoutDetails
{
    /**
     * @param array<string, string|null>|null $inlineAddress
     */
    public function __construct(
        public Fulfillment $fulfillment,
        public PaymentMethod $paymentMethod,
        public ?int $quantity = null,
        public ?string $savedAddressId = null,
        public ?array $inlineAddress = null,
    ) {}
}
