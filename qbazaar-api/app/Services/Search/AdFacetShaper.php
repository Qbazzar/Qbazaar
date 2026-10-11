<?php

declare(strict_types=1);

namespace App\Services\Search;

use stdClass;

/**
 * Turns facet distributions, keyed by index attribute as Meilisearch returns
 * them (`ad_type`, `custom_fields.make`…), into the two public shapes: the
 * `meta.facets` block and the older top-level `facets` block of `/search`.
 */
class AdFacetShaper
{
    /** Attributes of `meta.facets`, each a map of value to ad count. */
    public const array META_ATTRIBUTES = ['ad_type', 'shipping', 'seller_type', 'buy_now', 'condition'];

    /**
     * @param array<string, array<string, int>> $distribution
     * @param list<string> $customKeys select filters of the chosen category
     * @return array<string, mixed>
     */
    public function meta(array $distribution, array $customKeys): array
    {
        $facets = [];

        foreach (self::META_ATTRIBUTES as $attribute) {
            $facets[$attribute] = $this->counts($distribution[$attribute] ?? []);
        }

        if ($customKeys !== []) {
            $facets['custom_fields'] = [];
            foreach ($customKeys as $key) {
                $facets['custom_fields'][$key] = $this->counts($distribution['custom_fields.' . $key] ?? []);
            }
        }

        return $facets;
    }

    /**
     * @param array<string, array<string, int>> $distribution
     * @return array<string, mixed>
     */
    public function legacy(array $distribution): array
    {
        return [
            'categories' => $this->counts($distribution['category_slug'] ?? []),
            'locations' => $this->counts($distribution['location_slug'] ?? []),
            'conditions' => $this->counts($distribution['condition'] ?? []),
            'price_types' => $this->counts($distribution['price_type'] ?? []),
            'price_buckets' => $this->priceBuckets(),
        ];
    }

    /**
     * Always an object, so an empty map is `{}` and numeric-looking values
     * ("2015") never turn the map into a JSON list.
     *
     * @param array<string, int> $counts
     */
    private function counts(array $counts): stdClass
    {
        return (object) $counts;
    }

    /**
     * Static bucket scheme — keeps the UI simple. Real per-result histograms
     * land in a follow-up once we have query volume to size them on.
     *
     * @return list<array{label: string, min: float|null, max: float|null}>
     */
    private function priceBuckets(): array
    {
        return [
            ['label' => '0-100', 'min' => 0.0, 'max' => 100.0],
            ['label' => '100-500', 'min' => 100.0, 'max' => 500.0],
            ['label' => '500-1000', 'min' => 500.0, 'max' => 1000.0],
            ['label' => '1000-5000', 'min' => 1000.0, 'max' => 5000.0],
            ['label' => '5000+', 'min' => 5000.0, 'max' => null],
        ];
    }
}
