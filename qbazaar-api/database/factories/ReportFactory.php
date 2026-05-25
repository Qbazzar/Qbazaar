<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReportCategory;
use App\Enums\ReportStatus;
use App\Enums\ReportTarget;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Report>
 *
 * Builds a PENDING report against a freshly-faked ad target by default.
 * Tests can override `target_type` + `target_id` to point at any record.
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'target_type' => ReportTarget::AD->value,
            'target_id' => strtolower((string) Str::ulid()),
            'category' => ReportCategory::SPAM->value,
            'description' => fake()->optional()->sentence(),
            'status' => ReportStatus::PENDING->value,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'admin_notes' => null,
        ];
    }

    public function reviewed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReportStatus::REVIEWED->value,
            'reviewed_at' => now(),
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReportStatus::DISMISSED->value,
            'reviewed_at' => now(),
        ]);
    }

    public function actioned(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReportStatus::ACTIONED->value,
            'reviewed_at' => now(),
        ]);
    }
}
