<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\LocationSeeder;

/**
 * Test helper for Ad feature tests.
 *
 * Provides a single hook ({@see seedReferenceData}) to pre-load categories
 * and locations so factories can pick random rows without each test needing
 * to wire them up. Keeps Pest tests one-liner friendly.
 */
trait CreatesAds
{
    protected function seedReferenceData(): void
    {
        $this->seed([
            CategorySeeder::class,
            LocationSeeder::class,
        ]);
    }

    protected function makeAd(User $owner, array $overrides = []): Ad
    {
        return Ad::factory()->create(array_merge([
            'user_id' => $owner->id,
            'category_id' => Category::query()->inRandomOrder()->value('id'),
            'location_id' => Location::query()->inRandomOrder()->value('id'),
        ], $overrides));
    }
}
