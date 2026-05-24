<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdStatus;
use App\Enums\Condition;
use App\Enums\PriceType;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * @extends Factory<Ad>
 *
 * Categories + Locations MUST be seeded before invoking this factory in tests
 * (DatabaseSeeder takes care of that). We pick existing rows so the foreign
 * keys resolve without requiring the caller to wire them up explicitly.
 */
class AdFactory extends Factory
{
    protected $model = Ad::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->generateTitle();

        return [
            'user_id' => User::factory(),
            'category_id' => $this->randomCategoryId(),
            'location_id' => $this->randomLocationId(),
            'title' => $title,
            'description' => fake()->paragraph(rand(2, 5)),
            'price' => fake()->randomFloat(2, 100, 9999),
            'price_type' => PriceType::FIXED->value,
            'currency' => 'QAR',
            'condition' => fake()->randomElement([
                Condition::NEW->value,
                Condition::LIKE_NEW->value,
                Condition::USED->value,
            ]),
            'status' => AdStatus::DRAFT->value,
            'custom_fields' => null,
            'views_count' => 0,
            'favorites_count' => 0,
            'published_at' => null,
            'expires_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AdStatus::DRAFT->value,
            'published_at' => null,
            'expires_at' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AdStatus::PENDING->value,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AdStatus::ACTIVE->value,
            'published_at' => now()->subHours(rand(1, 48)),
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function sold(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AdStatus::SOLD->value,
            'published_at' => now()->subDays(rand(1, 10)),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AdStatus::EXPIRED->value,
            'published_at' => now()->subDays(60),
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Title between 10 and 60 characters — Faker's `sentence` is the cheapest
     * way to get realistic prose; we trim it to the window the validator
     * accepts.
     */
    private function generateTitle(): string
    {
        $candidate = fake()->sentence(rand(3, 8));

        // Trim trailing period so the visible length matches the constraint.
        $candidate = rtrim($candidate, '.');

        if (strlen($candidate) < 10) {
            $candidate .= ' ' . fake()->word();
        }

        return substr($candidate, 0, 60);
    }

    private function randomCategoryId(): string
    {
        /** @var Category|null $category */
        $category = Category::query()->inRandomOrder()->first();

        if ($category === null) {
            throw new RuntimeException(
                'AdFactory requires at least one Category. Run CategorySeeder first.',
            );
        }

        return $category->id;
    }

    private function randomLocationId(): string
    {
        /** @var Location|null $location */
        $location = Location::query()->inRandomOrder()->first();

        if ($location === null) {
            throw new RuntimeException(
                'AdFactory requires at least one Location. Run LocationSeeder first.',
            );
        }

        return $location->id;
    }
}
