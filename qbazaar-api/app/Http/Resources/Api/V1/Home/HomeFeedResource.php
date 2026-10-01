<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Home;

use App\Data\Catalog\HomeFeed;
use App\Http\Resources\Api\V1\Ads\AdSummaryResource;
use App\Http\Resources\Api\V1\Reference\CategoryResource;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Services\Catalog\ListingCounter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property HomeFeed $resource
 */
class HomeFeedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $feed = $this->resource;

        return [
            'categories' => $feed->categories
                ->map(fn (Category $category): array => (new CategoryResource($category))->toArray($request))
                ->values()
                ->all(),
            'recommended' => $this->ads($feed->recommended, $request),
            'featured_sellers' => $feed->featuredSellers
                ->map(fn (User $seller): array => (new SellerCardResource($seller))->toArray($request))
                ->values()
                ->all(),
            'best_selling' => $this->ads($feed->bestSelling, $request),
            'places' => $feed->places
                ->map(fn (Location $place): array => [
                    'id' => $place->id,
                    'slug' => $place->slug,
                    'name' => $place->name,
                    'type' => $place->type->value,
                    'lat' => $place->lat !== null ? (float) $place->lat : null,
                    'lng' => $place->lng !== null ? (float) $place->lng : null,
                    'ads_count' => ($feed->placeCounts[$place->id] ?? ListingCounter::empty())['ads_count'],
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param Collection<int, Ad> $ads
     * @return array<int, array<string, mixed>>
     */
    private function ads(Collection $ads, Request $request): array
    {
        return $ads
            ->map(fn (Ad $ad): array => (new AdSummaryResource($ad))->toArray($request))
            ->values()
            ->all();
    }
}
