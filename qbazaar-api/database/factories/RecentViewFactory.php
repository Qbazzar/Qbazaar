<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Ad;
use App\Models\RecentView;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecentView>
 */
class RecentViewFactory extends Factory
{
    protected $model = RecentView::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_id' => null,
            'ad_id' => Ad::factory()->active(),
            'viewed_at' => now(),
        ];
    }

    /**
     * Anonymous view variant — drops the user and provides a session id.
     */
    public function anonymous(string $sessionId): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
            'session_id' => $sessionId,
        ]);
    }
}
