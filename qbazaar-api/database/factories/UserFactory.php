<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\Language;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+974' . fake()->unique()->numerify('########'),
            'password' => static::$password ??= Hash::make('password'),
            'account_type' => AccountType::PRIVATE_INDIVIDUAL->value,
            'status' => UserStatus::ACTIVE->value,
            'email_verified' => false,
            'phone_verified' => false,
            'language' => Language::ARABIC->value,
            'avatar_url' => null,
            'last_login_at' => null,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::SUSPENDED->value,
        ]);
    }

    public function business(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::BUSINESS->value,
        ]);
    }
}
