<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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

    /** A draft that meets the publish requirements (enough images). */
    protected function makePublishableDraft(User $owner, array $overrides = []): Ad
    {
        $ad = $this->makeAd($owner, ['status' => 'draft', ...$overrides]);
        $this->attachImageRows($ad, (int) config('qbazaar.ads.min_images'));

        return $ad;
    }

    /**
     * Media rows without files, for tests that only need the ad's image count.
     */
    protected function attachImageRows(Ad $ad, int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            Media::query()->forceCreate([
                'model_type' => Ad::class,
                'model_id' => $ad->id,
                'uuid' => (string) Str::uuid(),
                'collection_name' => 'images',
                'name' => "image-{$i}",
                'file_name' => "image-{$i}.jpg",
                'mime_type' => 'image/jpeg',
                'disk' => 'public',
                'conversions_disk' => 'public',
                'size' => 1024,
                'manipulations' => [],
                'custom_properties' => [],
                'generated_conversions' => [],
                'responsive_images' => [],
                'order_column' => $i + 1,
            ]);
        }
    }
}
