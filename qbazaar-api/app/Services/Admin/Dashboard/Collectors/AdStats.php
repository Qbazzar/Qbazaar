<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard\Collectors;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Services\Admin\Dashboard\DashboardWindow;
use App\Services\Admin\Dashboard\Trend;

/** One grouped pass over `ads` (index `ads_status_created_idx`) for the ad tiles and the review queue. */
final class AdStats
{
    /**
     * Both views of one grouped query, so a request pays for it once.
     *
     * @return array{tiles: list<array<string, mixed>>, pending: array{count: int, oldest: string|null}, distribution: array<string, int>}
     */
    public function collect(DashboardWindow $window): array
    {
        $rows = $this->rows($window);

        return ['tiles' => $this->tiles($rows), 'pending' => $this->pendingReview($rows), 'distribution' => $this->distribution($rows)];
    }

    /**
     * @param array<string, object> $rows
     * @return list<array<string, mixed>>
     */
    private function tiles(array $rows): array
    {
        $count = static fn (AdStatus $status): int => (int) ($rows[$status->value]->total ?? 0);
        $created = fn (string $column): int => (int) collect($rows)->except(AdStatus::DRAFT->value)->sum($column);

        return [
            ['key' => 'ads_active', 'kind' => 'count', 'value' => $count(AdStatus::ACTIVE), 'href' => $this->listUrl(AdStatus::ACTIVE)],
            ['key' => 'ads_new', 'kind' => 'count', 'href' => $this->listUrl(null)] + Trend::between($created('new_now'), $created('new_before')),
            ['key' => 'ads_pending', 'kind' => 'count', 'value' => $count(AdStatus::PENDING), 'href' => $this->listUrl(AdStatus::PENDING)],
            ['key' => 'ads_expired', 'kind' => 'count', 'value' => $count(AdStatus::EXPIRED), 'href' => $this->listUrl(AdStatus::EXPIRED)],
            ['key' => 'ads_rejected', 'kind' => 'count', 'value' => $count(AdStatus::REJECTED), 'href' => $this->listUrl(AdStatus::REJECTED)],
        ];
    }

    /**
     * Ads per status, every status present (zero when empty), for the donut.
     *
     * @param array<string, object> $rows
     * @return array<string, int>
     */
    private function distribution(array $rows): array
    {
        $counts = [];
        foreach (AdStatus::cases() as $status) {
            $counts[$status->value] = (int) ($rows[$status->value]->total ?? 0);
        }

        return $counts;
    }

    /**
     * @param array<string, object> $rows
     * @return array{count: int, oldest: string|null}
     */
    private function pendingReview(array $rows): array
    {
        $count = (int) ($rows[AdStatus::PENDING->value]->total ?? 0);
        // Only looked up when something is waiting; the status filter keeps it on the status index.
        $oldest = $count > 0
            ? Ad::query()->where('status', AdStatus::PENDING->value)->toBase()->selectRaw('MIN(COALESCE(submitted_at, created_at)) as oldest')->value('oldest')
            : null;

        return ['count' => $count, 'oldest' => $oldest];
    }

    /**
     * @return array<string, object>
     */
    private function rows(DashboardWindow $window): array
    {
        return Ad::query()
            ->selectRaw(
                'status, COUNT(*) as total, '
                . 'COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as new_now, '
                . 'COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END), 0) as new_before',
                [$window->start, $window->previousStart, $window->previousEnd],
            )
            ->groupBy('status')
            ->toBase()
            ->get()
            ->keyBy('status')
            ->all();
    }

    private function listUrl(?AdStatus $status): string
    {
        return route('admin.ads.index', $status ? ['status' => $status->value] : [], false);
    }
}
