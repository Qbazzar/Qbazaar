<?php

declare(strict_types=1);

namespace App\Data\Catalog;

use App\Enums\AdSort;

final readonly class AdFeedFilters
{
    public function __construct(
        public ?string $categoryId = null,
        public ?string $locationId = null,
        public ?float $priceMin = null,
        public ?float $priceMax = null,
        public AdSort $sort = AdSort::LATEST,
    ) {}
}
