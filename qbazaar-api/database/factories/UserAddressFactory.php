<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAddress>
 */
class UserAddressFactory extends Factory
{
    protected $model = UserAddress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Home', 'Work', null]),
            'full_name' => fake()->name(),
            'phone' => '+9745' . fake()->numerify('#######'),
            'street' => fake()->streetName(),
            'house_number' => (string) fake()->numberBetween(1, 300),
            'supplement' => null,
            'city' => 'Doha',
            'postal_code' => null,
            'location_id' => null,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
