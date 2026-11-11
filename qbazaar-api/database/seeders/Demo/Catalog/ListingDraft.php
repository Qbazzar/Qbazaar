<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Catalog;

use App\Enums\PriceType;

final readonly class ListingDraft
{
    /**
     * @param array<string, mixed>|null $customFields
     */
    public function __construct(
        public string $title,
        public string $description,
        public ?string $price,
        public PriceType $priceType,
        public bool $hasCondition,
        public ?array $customFields,
    ) {}
}
