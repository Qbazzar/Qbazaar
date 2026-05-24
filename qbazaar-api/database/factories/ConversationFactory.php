<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Ad;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 *
 * The factory expects the caller to supply at least `ad_id` + `buyer_id`
 * (or override them). When `ad_id` is filled but `seller_id` is not, we
 * derive the seller from the ad's owner — matching how
 * StartConversationAction snapshots it in production.
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ad_id' => Ad::factory(),
            'buyer_id' => User::factory(),
            'seller_id' => User::factory(),
            'last_message_at' => null,
            'last_message_preview' => null,
        ];
    }
}
