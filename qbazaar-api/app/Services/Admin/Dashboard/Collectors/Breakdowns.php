<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard\Collectors;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use Illuminate\Database\Eloquent\Model;

/**
 * Where the active ads are: the ten busiest categories and places. Each is a
 * grouped count on the (category|location, status, published_at) indexes plus
 * one lookup of the ten names.
 */
final class Breakdowns
{
    private const int LIMIT = 10;

    /**
     * @return array<int, array{name: array<string, mixed>, count: int}>
     */
    public function categories(): array
    {
        return $this->top('category_id', Category::class);
    }

    /**
     * @return array<int, array{name: array<string, mixed>, count: int}>
     */
    public function locations(): array
    {
        return $this->top('location_id', Location::class);
    }

    /**
     * @param class-string<Model> $model
     * @return array<int, array{name: array<string, mixed>, count: int}>
     */
    private function top(string $column, string $model): array
    {
        $counts = Ad::query()
            ->where('status', AdStatus::ACTIVE->value)
            ->selectRaw("{$column} as id, COUNT(*) as total")
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit(self::LIMIT)
            ->toBase()
            ->pluck('total', 'id');

        $names = $model::query()->whereIn('id', $counts->keys())->pluck('name', 'id');

        return $counts->map(static fn ($total, $id): array => ['name' => (array) ($names[$id] ?? []), 'count' => (int) $total])
            ->values()
            ->all();
    }
}
