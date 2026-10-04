<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PurchaseRequestStatus;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequest>
 */
class PurchaseRequestFactory extends Factory
{
    protected $model = PurchaseRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'ad_id' => Ad::factory(),
            'buyer_id' => User::factory(),
            'seller_id' => User::factory(),
            'unit_price' => '250.00',
            'quantity' => 1,
            'currency' => 'QAR',
            'status' => PurchaseRequestStatus::PENDING,
        ];
    }
}
