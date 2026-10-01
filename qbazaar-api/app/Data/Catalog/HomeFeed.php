<?php

declare(strict_types=1);

namespace App\Data\Catalog;

use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final readonly class HomeFeed
{
    /**
     * @param Collection<int, Category> $categories
     * @param Collection<int, Ad> $recommended
     * @param Collection<int, User> $featuredSellers each with `listed_ads_count`
     * @param Collection<int, Ad> $bestSelling
     * @param Collection<int, Location> $places
     * @param array<string, array{ads_count: int, today_count: int, own_count: int}> $placeCounts keyed by location id
     */
    public function __construct(
        public Collection $categories,
        public Collection $recommended,
        public Collection $featuredSellers,
        public Collection $bestSelling,
        public Collection $places,
        public array $placeCounts,
    ) {}
}
