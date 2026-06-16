<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceToken>
 *
 * Builds a web-platform token by default. The 152-char URL-safe string
 * mirrors the shape of a real FCM registration token.
 */
class DeviceTokenFactory extends Factory
{
    protected $model = DeviceToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token' => fake()->regexify('[A-Za-z0-9_-]{152}'),
            'platform' => 'web',
            'last_used_at' => now(),
        ];
    }
}
