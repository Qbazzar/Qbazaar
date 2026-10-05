<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Enums\AdSort;

/**
 * Translates a validated search request into the Meilisearch filter
 * expression and sort list. Kept free of I/O so it can be tested without
 * a running engine.
 */
class AdSearchCriteria
{
    /**
     * Paid promotions lead the orders that have no explicit key of the
     * buyer's own (newest, most viewed); a price or distance sort is
     * honoured as asked.
     */
    private const string PROMOTED_FIRST = 'promotion_rank:desc';

    /**
     * @param array<string, mixed> $params
     */
    public function filter(array $params): string
    {
        // Only active ads are indexed; pinning the status here keeps an
        // indexer regression from leaking drafts into public results.
        $clauses = ['status = "active"'];

        // The *_path arrays hold every ancestor, so a parent id also matches ads in its descendants.
        if ($this->filled($params, 'category_id')) {
            $clauses[] = sprintf('category_path = "%s"', $this->quote($params['category_id']));
        }

        if ($this->filled($params, 'location_id')) {
            $clauses[] = sprintf('location_path = "%s"', $this->quote($params['location_id']));
        }

        if ($this->filled($params, 'condition')) {
            $clauses[] = sprintf('condition = "%s"', $this->quote($params['condition']));
        }

        if ($this->filled($params, 'price_type')) {
            $clauses[] = sprintf('price_type = "%s"', $this->quote($params['price_type']));
        }

        foreach (['ad_type', 'shipping'] as $field) {
            if ($this->filled($params, $field)) {
                $clauses[] = sprintf('%s = "%s"', $field, $this->quote($params[$field]));
            }
        }

        if (is_numeric($params['price_min'] ?? null)) {
            $clauses[] = sprintf('price >= %s', (float) $params['price_min']);
        }

        if (is_numeric($params['price_max'] ?? null)) {
            $clauses[] = sprintf('price <= %s', (float) $params['price_max']);
        }

        $point = $this->point($params);
        if ($point !== null && is_numeric($params['radius_km'] ?? null)) {
            $clauses[] = sprintf('_geoRadius(%s, %s, %d)', $point[0], $point[1], (int) round((float) $params['radius_km'] * 1000));
        }

        return implode(' AND ', [...$clauses, ...$this->customFieldClauses($params['custom_fields'] ?? null)]);
    }

    /**
     * @param array<string, mixed> $params
     * @return list<string>
     */
    public function sort(array $params): array
    {
        $sort = AdSort::tryFrom(is_string($params['sort'] ?? null) ? $params['sort'] : '') ?? AdSort::LATEST;

        $point = $this->point($params);

        if ($sort === AdSort::DISTANCE && $point !== null) {
            return [sprintf('_geoPoint(%s, %s):asc', $point[0], $point[1]), 'published_at:desc'];
        }

        return match ($sort) {
            AdSort::OLDEST => ['published_at:asc'],
            AdSort::PRICE_ASC => ['price:asc'],
            AdSort::PRICE_DESC => ['price:desc'],
            AdSort::MOST_VIEWED => [self::PROMOTED_FIRST, 'views_count:desc', 'published_at:desc'],
            AdSort::LATEST, AdSort::DISTANCE => [self::PROMOTED_FIRST, 'published_at:desc'],
        };
    }

    /**
     * Category-specific filters: custom_fields[make]=Toyota (equality) or
     * custom_fields[year][min]=2015&custom_fields[year][max]=2020 (range).
     *
     * @return list<string>
     */
    private function customFieldClauses(mixed $customFields): array
    {
        if (! is_array($customFields)) {
            return [];
        }

        $clauses = [];

        foreach ($customFields as $key => $value) {
            // Keys are interpolated as attribute paths, so anything beyond a plain identifier is dropped.
            if (! is_string($key) || preg_match('/^[a-z0-9_]+$/i', $key) !== 1) {
                continue;
            }

            if (is_array($value)) {
                if (is_numeric($value['min'] ?? null)) {
                    $clauses[] = sprintf('custom_fields.%s >= %s', $key, (float) $value['min']);
                }
                if (is_numeric($value['max'] ?? null)) {
                    $clauses[] = sprintf('custom_fields.%s <= %s', $key, (float) $value['max']);
                }
            } elseif (is_string($value) && $value !== '') {
                $clauses[] = sprintf('custom_fields.%s = "%s"', $key, $this->quote($value));
            }
        }

        return $clauses;
    }

    /**
     * @param array<string, mixed> $params
     * @return array{0: string, 1: string}|null
     */
    private function point(array $params): ?array
    {
        $lat = $params['lat'] ?? null;
        $lng = $params['lng'] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return [$this->coordinate((float) $lat), $this->coordinate((float) $lng)];
    }

    private function coordinate(float $value): string
    {
        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    }

    /**
     * @param array<string, mixed> $params
     */
    private function filled(array $params, string $key): bool
    {
        return is_string($params[$key] ?? null) && $params[$key] !== '';
    }

    private function quote(mixed $value): string
    {
        return addcslashes((string) $value, '"\\');
    }
}
