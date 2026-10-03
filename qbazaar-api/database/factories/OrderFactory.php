<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Ad;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 *
 * A cash order of one item at 100.00 with a 5% commission. Real flows create
 * orders through OrderPlacementService; this is for tests that need a row
 * in a given state.
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ad_id' => Ad::factory(),
            'buyer_id' => User::factory(),
            'seller_id' => User::factory(),
            'source' => OrderSource::OFFER->value,
            'source_id' => (string) Str::ulid(),
            'status' => OrderStatus::CREATED->value,
            'ad_title' => fake()->sentence(4),
            'currency' => 'QAR',
            'unit_price' => '100.00',
            'quantity' => 1,
            'shipping_fee' => '0.00',
            'total' => '100.00',
            'commission_rate' => '5.00',
            'commission_amount' => '5.00',
            'payment_method' => PaymentMethod::CASH->value,
        ];
    }

    public function status(OrderStatus $status): static
    {
        $column = $status->timestampColumn();

        return $this->state(fn (): array => array_filter([
            'status' => $status->value,
            ...($column === null ? [] : [$column => now()]),
        ]));
    }
}
