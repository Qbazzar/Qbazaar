<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Ad;
use Illuminate\Support\Carbon;

/**
 * Counts publicly listed ads per node of a tree and rolls every count up to
 * the node's ancestors, using one grouped query regardless of the tree size.
 */
class ListingCounter
{
    /**
     * @param 'category_id'|'location_id' $column
     * @return array<string, array{ads_count: int, today_count: int, own_count: int}>
     */
    public function countBy(string $column, CachedHierarchy $hierarchy): array
    {
        $rows = Ad::query()
            ->publiclyListed()
            ->toBase()
            ->select($column)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN published_at >= ? THEN 1 ELSE 0 END) as today', [$this->startOfToday()])
            ->groupBy($column)
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $nodeId = (string) $row->{$column};
            $total = (int) $row->total;
            $today = (int) $row->today;

            $counts[$nodeId] ??= self::empty();
            $counts[$nodeId]['own_count'] += $total;

            foreach ($hierarchy->pathTo($nodeId) as $ancestorId) {
                $counts[$ancestorId] ??= self::empty();
                $counts[$ancestorId]['ads_count'] += $total;
                $counts[$ancestorId]['today_count'] += $today;
            }
        }

        return $counts;
    }

    /**
     * @return array{ads_count: int, today_count: int, own_count: int}
     */
    public static function empty(): array
    {
        return ['ads_count' => 0, 'today_count' => 0, 'own_count' => 0];
    }

    /**
     * "Today" follows the marketplace's local day, not the UTC day the rows are stored in.
     */
    private function startOfToday(): Carbon
    {
        return Carbon::now((string) config('qbazaar.timezone_display'))
            ->startOfDay()
            ->setTimezone((string) config('app.timezone'));
    }
}
