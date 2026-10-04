<?php

declare(strict_types=1);

namespace App\Data\Orders;

/**
 * Server-side checkout amounts, as two-decimal strings. The commission is
 * charged on the items only, never on shipping.
 */
final readonly class CheckoutQuote
{
    public function __construct(
        public string $unitPrice,
        public int $quantity,
        public string $itemsSubtotal,
        public string $shippingFee,
        public string $total,
        public string $commission,
    ) {}

    /**
     * The buyer's view: the commission is the seller's business.
     *
     * @return array<string, string|int>
     */
    public function forBuyer(): array
    {
        return [
            'unit_price' => $this->unitPrice,
            'quantity' => $this->quantity,
            'items_subtotal' => $this->itemsSubtotal,
            'shipping_fee' => $this->shippingFee,
            'total' => $this->total,
        ];
    }
}
