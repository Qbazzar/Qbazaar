<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use App\Data\Orders\CheckoutDetails;
use App\Enums\Fulfillment;
use App\Enums\PaymentMethod;
use App\Http\Requests\Api\V1\Account\Concerns\ValidatesAddressFields;
use App\Services\Payments\PaymentGateways;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body for `POST /orders/{id}/checkout`. Delivery needs either a saved
 * address (`address_id`) or an inline `address`, never both; pickup takes
 * neither. Only the payment methods with a configured gateway are accepted.
 *
 * @bodyParam fulfillment string `pickup` or `delivery`. Example: delivery
 * @bodyParam address_id string A saved address of the buyer. Example: 01J0000000000000000000000
 * @bodyParam address object Inline delivery address, same fields as a saved address.
 * @bodyParam quantity integer Units, for orders from a "Buy Now" request. Example: 1
 * @bodyParam payment_method string Example: cash
 */
class CheckoutOrderRequest extends FormRequest
{
    use ValidatesAddressFields;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isDelivery = $this->input('fulfillment') === Fulfillment::DELIVERY->value;
        $methods = array_map(fn (PaymentMethod $method): string => $method->value, app(PaymentGateways::class)->methods());

        return [
            'fulfillment' => ['required', Rule::enum(Fulfillment::class)],
            'payment_method' => ['required', 'string', Rule::in($methods)],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:' . (int) config('qbazaar.ads.quantity_max')],
            'address_id' => [Rule::requiredIf($isDelivery && ! $this->has('address')), Rule::prohibitedIf(! $isDelivery), 'prohibits:address', 'string', 'ulid'],
            'address' => [Rule::prohibitedIf(! $isDelivery), 'array'],
            ...$this->addressFieldRules('required_with:address', 'address.'),
        ];
    }

    public function details(): CheckoutDetails
    {
        $quantity = $this->validated('quantity');
        $addressId = $this->validated('address_id');

        /** @var array<string, string|null>|null $address */
        $address = $this->validated('address');

        return new CheckoutDetails(
            fulfillment: Fulfillment::from((string) $this->validated('fulfillment')),
            paymentMethod: PaymentMethod::from((string) $this->validated('payment_method')),
            quantity: $quantity === null ? null : (int) $quantity,
            savedAddressId: is_string($addressId) ? $addressId : null,
            inlineAddress: $address,
        );
    }
}
