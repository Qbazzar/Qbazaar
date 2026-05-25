<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OfferStatus;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 *
 * Builds a PENDING offer by default. The convenience states
 * ({@see accepted()} / {@see rejected()} / {@see withdrawn()} /
 * {@see expired()}) flip the status field and stamp the matching
 * terminal timestamp so test setups can spell intent in one line.
 *
 * Most call-sites will already have a Conversation built and pass
 * `['conversation_id' => …, 'ad_id' => …, 'buyer_id' => …, 'seller_id' => …]`
 * explicitly — the per-relation factory fallbacks here exist mainly so
 * factory-only tests (model casts, scopes) still work without a wiring
 * dance.
 */
class OfferFactory extends Factory
{
    protected $model = Offer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $expiryDays = (int) config('qbazaar.offers.expiry_days', 7);

        return [
            'conversation_id' => Conversation::factory(),
            'ad_id' => Ad::factory(),
            'buyer_id' => User::factory(),
            'seller_id' => User::factory(),
            'message_id' => null,
            'amount' => fake()->randomFloat(2, 50, 5_000),
            'currency' => 'QAR',
            'note' => null,
            'status' => OfferStatus::PENDING->value,
            'expires_at' => now()->addDays($expiryDays),
            'accepted_at' => null,
            'rejected_at' => null,
            'withdrawn_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OfferStatus::PENDING->value,
            'accepted_at' => null,
            'rejected_at' => null,
            'withdrawn_at' => null,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OfferStatus::ACCEPTED->value,
            'accepted_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OfferStatus::REJECTED->value,
            'rejected_at' => now(),
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OfferStatus::WITHDRAWN->value,
            'withdrawn_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OfferStatus::EXPIRED->value,
            'expires_at' => now()->subDay(),
        ]);
    }
}
