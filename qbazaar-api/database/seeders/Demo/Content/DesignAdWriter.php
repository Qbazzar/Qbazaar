<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Content;

use App\Enums\AdShipping;
use App\Enums\AdStatus;
use App\Enums\Condition;
use App\Enums\PriceType;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Services\Ads\CustomFieldsValidator;
use Database\Seeders\Demo\DemoMedia;
use Illuminate\Support\Carbon;

/**
 * Creates or refreshes one listing of the design for an owner. An ad is
 * identified by its owner and title, so running it again updates the same
 * row and its photos instead of adding a second copy.
 *
 * @phpstan-import-type DesignAd from DesignManifest
 */
final class DesignAdWriter
{
    private const int VIEWS_BASE = 40;

    private const int VIEWS_SPREAD = 2_400;

    public function __construct(
        private readonly DemoMedia $media,
        private readonly CustomFieldsValidator $fieldsValidator,
    ) {}

    /**
     * @param DesignAd $design
     * @param AdStatus $status status of a new listing; an existing one keeps its own
     */
    public function ensure(array $design, User $owner, Category $category, Location $district, AdStatus $status): Ad
    {
        $language = $owner->language->value;
        $existing = Ad::query()->with('media')->where('user_id', $owner->id)->where('title', $design['title'][$language])->first();
        $ad = $existing ?? new Ad;

        $ad->forceFill($this->attributes($design, $owner, $category, $district, $language));

        if ($existing === null) {
            $ad->forceFill($this->creationAttributes($design, $status));
        }

        $ad->save();
        $this->media->syncDesignPhotos($ad, $design['images']);

        return $ad;
    }

    /**
     * @param DesignAd $design
     * @return array<string, mixed>
     */
    private function attributes(array $design, User $owner, Category $category, Location $district, string $language): array
    {
        $priceType = PriceType::from($design['price_type']);

        return [
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'location_id' => $district->id,
            'latitude' => $district->lat,
            'longitude' => $district->lng,
            'title' => $design['title'][$language],
            'description' => $design['description'][$language],
            'price' => $priceType->requiresNullPrice() ? null : (string) $design['price'],
            'price_type' => $priceType,
            'currency' => $design['currency'],
            'condition' => $design['condition'] === null ? null : Condition::from($design['condition']),
            'shipping' => AdShipping::PICKUP_ONLY,
            'quantity' => 1,
            'custom_fields' => $this->customFields($design, $category),
        ];
    }

    /**
     * @param DesignAd $design
     * @return array<string, mixed>
     */
    private function creationAttributes(array $design, AdStatus $status): array
    {
        $postedAt = Carbon::now()->subMinutes($design['posted_minutes_ago']);
        $live = $status === AdStatus::ACTIVE;

        return [
            'status' => $status,
            'submitted_at' => $status === AdStatus::DRAFT ? null : $postedAt,
            'published_at' => $live ? $postedAt : null,
            'expires_at' => $live ? $postedAt->copy()->addDays((int) config('qbazaar.ads.lifetime_days')) : null,
            'featured' => $live && in_array('featured', $design['badges'], true),
            'views_count' => self::VIEWS_BASE + crc32($design['key']) % self::VIEWS_SPREAD,
            'created_at' => $postedAt,
            'updated_at' => $postedAt,
        ];
    }

    /**
     * @param DesignAd $design
     * @return array<string, mixed>|null
     */
    private function customFields(array $design, Category $category): ?array
    {
        if ($category->custom_fields === null) {
            return null;
        }

        return $this->fieldsValidator->validate($category, $design['custom_fields'] ?? null);
    }
}
