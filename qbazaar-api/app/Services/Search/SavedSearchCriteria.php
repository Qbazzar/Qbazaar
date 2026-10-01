<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Enums\Condition;
use App\Enums\PriceType;
use App\Models\Category;
use App\Models\Location;
use App\Models\SavedSearch;
use Illuminate\Database\Eloquent\Model;

/**
 * Mirrors the structured filters of a saved search's `query_params` into
 * its indexed columns, resolving slugs the same way `/search` does.
 */
class SavedSearchCriteria
{
    public function apply(SavedSearch $search): void
    {
        $params = $search->query_params;

        $search->forceFill([
            'category_id' => $this->resolveId(Category::class, $params['category_id'] ?? null, $params['category_slug'] ?? null),
            'location_id' => $this->resolveId(Location::class, $params['location_id'] ?? null, $params['location_slug'] ?? null),
            'condition' => Condition::tryFrom($this->string($params['condition'] ?? null))?->value,
            'price_type' => PriceType::tryFrom($this->string($params['price_type'] ?? null))?->value,
            'price_min' => is_numeric($params['price_min'] ?? null) ? (string) $params['price_min'] : null,
            'price_max' => is_numeric($params['price_max'] ?? null) ? (string) $params['price_max'] : null,
        ]);
    }

    /**
     * @param class-string<Category|Location> $model
     */
    private function resolveId(string $model, mixed $id, mixed $slug): ?string
    {
        $column = match (true) {
            is_string($id) && $id !== '' => 'id',
            is_string($slug) && $slug !== '' => 'slug',
            default => null,
        };

        if ($column === null) {
            return null;
        }

        /** @var Model|null $row */
        $row = $model::query()->where($column, $column === 'id' ? $id : $slug)->first(['id']);

        return $row !== null ? (string) $row->getKey() : SavedSearch::MATCHES_NOTHING;
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
