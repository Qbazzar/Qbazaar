<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PromotionPaymentMethod;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Models\Ad;
use App\Models\AdPromotion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdPromotion>
 *
 * A week of highlight paid by bank transfer and waiting for the admin. Real
 * flows go through PromotionLifecycleService; this is for tests that need a
 * row in a given state.
 */
class AdPromotionFactory extends Factory
{
    protected $model = AdPromotion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ad_id' => Ad::factory(),
            'user_id' => User::factory(),
            'type' => PromotionType::HIGHLIGHT->value,
            'status' => PromotionStatus::PENDING_PAYMENT->value,
            'payment_method' => PromotionPaymentMethod::BANK_TRANSFER->value,
            'price' => '15.00',
            'currency' => 'QAR',
            'duration_days' => 7,
            'transfer_reference' => 'TRX-' . fake()->numerify('######'),
        ];
    }
}
