<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BusinessApplicationStatus;
use App\Models\BusinessApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessApplication>
 */
class BusinessApplicationFactory extends Factory
{
    protected $model = BusinessApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => BusinessApplicationStatus::PENDING,
            'business_name' => fake()->company(),
            'legal_name' => fake()->company() . ' W.L.L.',
            'commercial_registration_number' => (string) fake()->numberBetween(100000, 999999),
            'applicant_note' => null,
            'submitted_at' => now(),
        ];
    }

    public function needsInfo(): static
    {
        return $this->state(fn (): array => [
            'status' => BusinessApplicationStatus::NEEDS_INFO,
            'review_note' => 'Please attach the commercial registration.',
            'reviewed_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => BusinessApplicationStatus::APPROVED,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => BusinessApplicationStatus::REJECTED,
            'review_note' => 'The registration number does not match.',
            'reviewed_at' => now(),
        ]);
    }
}
