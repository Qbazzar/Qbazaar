<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enums\PlatformSetting;
use App\Models\CategoryCommissionRate;
use App\Services\Catalog\CategoryHierarchy;
use App\Services\Settings\SettingsService;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The commission percent for a category: its own override, else the nearest
 * ancestor's, else the general platform rate. Overrides are few, so they are
 * cached as one map and the category chain comes from the cached hierarchy;
 * resolving a rate runs no query once both caches are warm.
 */
class CommissionRates
{
    private const string CACHE_KEY = 'commission_rates.by_category';

    public function __construct(
        private readonly SettingsService $settings,
        private readonly CategoryHierarchy $categories,
    ) {}

    public function rateFor(?string $categoryId): string
    {
        $overrides = $this->overrides();

        foreach (array_reverse($this->categories->pathTo($categoryId)) as $id) {
            if (isset($overrides[$id])) {
                return $overrides[$id];
            }
        }

        return $this->settings->decimal(PlatformSetting::COMMISSION_RATE);
    }

    /**
     * Commission is charged on the item price, not on shipping, and rounded
     * half-up to two decimals.
     */
    public function commissionOn(string $itemsSubtotal, string $rate): string
    {
        return Money::percentOf($itemsSubtotal, $rate);
    }

    /**
     * @return array<string, string> rate keyed by category id
     */
    public function overrides(): array
    {
        /** @var array<string, string> */
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => CategoryCommissionRate::query()
            ->get(['category_id', 'rate'])
            ->mapWithKeys(fn (CategoryCommissionRate $row): array => [$row->category_id => $row->rate])
            ->all());
    }

    public function forget(): void
    {
        // Forgetting before the commit would let a concurrent read re-cache the old rows.
        DB::afterCommit(fn () => Cache::forget(self::CACHE_KEY));
    }
}
