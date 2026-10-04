<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Orders;

use App\Data\Orders\CheckoutQuote;
use App\Data\Orders\CheckoutSummary;
use App\Enums\Fulfillment;
use App\Enums\PaymentMethod;
use App\Http\Resources\Api\V1\Account\AddressResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `GET /orders/{id}/checkout`. Amounts are decimal strings in the order's
 * currency; `quotes` holds the totals per available fulfillment.
 *
 * @property CheckoutSummary $resource
 */
class CheckoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $summary = $this->resource;

        return [
            'order' => (new OrderResource($summary->order))->resolve($request),
            'currency' => $summary->order->currency,
            'fulfillment_options' => array_map(fn (Fulfillment $option): string => $option->value, $summary->fulfillmentOptions),
            'delivery_fee' => $summary->deliveryFee,
            'max_quantity' => $summary->maxQuantity,
            'payment_methods' => array_map(fn (PaymentMethod $method): string => $method->value, $summary->paymentMethods),
            'saved_addresses' => AddressResource::collection($summary->savedAddresses)->resolve($request),
            'quotes' => array_map(fn (CheckoutQuote $quote): array => $quote->forBuyer(), $summary->quotes),
        ];
    }
}
