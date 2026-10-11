<?php

declare(strict_types=1);

namespace App\Data\Catalog;

use App\Enums\AdSort;

/**
 * The filters of a public ad list, as the database reads them. The search
 * engine path speaks the same filters as a params array (see
 * {@see self::fromParams()}), so `/ads` and `/search` mean the same thing.
 */
final readonly class AdFeedFilters
{
    /**
     * @param array<string, string|array{min?: mixed, max?: mixed}> $customFields
     */
    public function __construct(
        public ?string $categoryId = null,
        public ?string $locationId = null,
        public ?float $priceMin = null,
        public ?float $priceMax = null,
        public AdSort $sort = AdSort::LATEST,
        public ?string $keyword = null,
        public ?string $condition = null,
        public ?string $priceType = null,
        public ?string $adType = null,
        public ?string $shipping = null,
        public ?string $sellerType = null,
        public bool $buyNow = false,
        public array $customFields = [],
    ) {}

    /**
     * @param array<string, mixed> $params validated request input
     */
    public static function fromParams(array $params): self
    {
        $text = static fn (string $key): ?string => is_string($params[$key] ?? null) && $params[$key] !== '' ? $params[$key] : null;
        $fields = is_array($params['custom_fields'] ?? null) ? $params['custom_fields'] : [];

        return new self(
            categoryId: $text('category_id'),
            locationId: $text('location_id'),
            priceMin: is_numeric($params['price_min'] ?? null) ? (float) $params['price_min'] : null,
            priceMax: is_numeric($params['price_max'] ?? null) ? (float) $params['price_max'] : null,
            sort: AdSort::tryFrom((string) ($params['sort'] ?? '')) ?? AdSort::LATEST,
            keyword: ($keyword = $text('q')) !== null ? trim($keyword) : null,
            condition: $text('condition'),
            priceType: $text('price_type'),
            adType: $text('ad_type'),
            shipping: $text('shipping'),
            sellerType: $text('seller_type'),
            buyNow: filter_var($params['buy_now'] ?? false, FILTER_VALIDATE_BOOLEAN),
            customFields: $fields,
        );
    }

    /**
     * Whether any filter beyond category, location and price is set. The warmed
     * category and location counters only answer for the plain shapes.
     */
    public function hasDetailFilters(): bool
    {
        return $this->keyword !== null && $this->keyword !== ''
            || $this->condition !== null
            || $this->priceType !== null
            || $this->adType !== null
            || $this->shipping !== null
            || $this->sellerType !== null
            || $this->buyNow
            || $this->customFields !== [];
    }

    /**
     * Identifies the filter set, ignoring the sort, for keys of values shared per filter set.
     */
    public function fingerprint(): string
    {
        // Sorted, so the same filters in another query-string order share one key.
        $fields = $this->customFields;
        ksort($fields);
        $fields = array_map(static function (mixed $value): mixed {
            if (is_array($value)) {
                ksort($value);
            }

            return $value;
        }, $fields);

        return sha1((string) json_encode([
            $this->categoryId, $this->locationId, $this->priceMin, $this->priceMax, $this->keyword,
            $this->condition, $this->priceType, $this->adType, $this->shipping, $this->sellerType,
            $this->buyNow, $fields,
        ]));
    }
}
