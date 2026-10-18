<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Data\Catalog\HomeFeed;
use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Models\Ad;
use App\Models\Location;
use App\Models\User;
use App\Services\Catalog\CategoryTree;
use App\Services\Catalog\LocationAdCounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Everything the home screen shows, gathered in one pass.
 *
 * Until the admin can curate the sections (AD-17.1, AD-17.2) they run in
 * automatic mode: recommended = paid promotions first, then the newest ads of
 * the last 24 hours (topped up with the most viewed); best selling = most favourited; featured sellers =
 * business accounts with the most live ads.
 */
class GetHomeFeedAction
{
    private const AD_RELATIONS = ['category', 'location', 'primaryImage'];

    public function __construct(
        private readonly CategoryTree $categoryTree,
        private readonly LocationAdCounts $placeCounts,
    ) {}

    public function execute(): HomeFeed
    {
        return new HomeFeed(
            categories: $this->categoryTree->roots(),
            recommended: $this->recommended(),
            featuredSellers: $this->featuredSellers(),
            bestSelling: $this->bestSelling(),
            places: $this->places(),
            placeCounts: $this->placeCounts->all(),
        );
    }

    /**
     * Promoted ads, then the newest ads of the last day, then (only to fill
     * the rail) the most viewed of the last month.
     *
     * @return Collection<int, Ad>
     */
    private function recommended(): Collection
    {
        $limit = (int) config('qbazaar.home.recommended_limit');
        $rail = $this->promoted(min($limit, (int) config('qbazaar.promotions.home_promoted_limit')));

        $rail = $rail->concat($this->fresh($limit - $rail->count(), $rail->modelKeys()));

        // Relations are loaded once for the whole rail, so the query count
        // does not depend on which of the three groups happen to be empty.
        return $rail->concat($this->mostViewed($limit - $rail->count(), $rail->modelKeys()))
            ->load(self::AD_RELATIONS);
    }

    /**
     * @param array<int, int|string> $exclude
     * @return Collection<int, Ad>
     */
    private function fresh(int $limit, array $exclude): Collection
    {
        if ($limit < 1) {
            return new Collection;
        }

        $since = now()->subHours((int) config('qbazaar.home.recommended_window_hours'));

        return $this->railQuery($limit, $exclude)
            ->where('published_at', '>=', $since)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param array<int, int|string> $exclude
     * @return Collection<int, Ad>
     */
    private function mostViewed(int $limit, array $exclude): Collection
    {
        if ($limit < 1) {
            return new Collection;
        }

        $since = now()->subDays((int) config('qbazaar.home.recommended_window_days'));

        return $this->railQuery($limit, $exclude)
            ->where('published_at', '>=', $since)
            ->orderByDesc('views_count')
            ->orderByDesc('published_at')
            ->get();
    }

    /**
     * @param array<int, int|string> $exclude
     * @return Builder<Ad>
     */
    private function railQuery(int $limit, array $exclude): Builder
    {
        return Ad::query()
            ->publiclyListed()
            ->whereKeyNot($exclude)
            ->limit($limit);
    }

    /**
     * Promoted ads, strongest promotion first. The (status, seller_active,
     * promotion_rank) index keeps this to the few promoted rows.
     *
     * @return Collection<int, Ad>
     */
    private function promoted(int $limit): Collection
    {
        if ($limit < 1) {
            return new Collection;
        }

        return Ad::query()
            ->publiclyListed()
            ->where('promotion_rank', '>', 0)
            ->orderByDesc('promotion_rank')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Ad>
     */
    private function bestSelling(): Collection
    {
        return Ad::query()
            ->publiclyListed()
            ->orderByDesc('favorites_count')
            ->orderByDesc('published_at')
            ->limit((int) config('qbazaar.home.best_selling_limit'))
            ->with(self::AD_RELATIONS)
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function featuredSellers(): Collection
    {
        return User::query()
            ->where('account_type', AccountType::BUSINESS->value)
            ->where('status', UserStatus::ACTIVE->value)
            ->where('active_ads_count', '>', 0)
            ->orderByDesc('active_ads_count')
            ->orderBy('id')
            ->limit((int) config('qbazaar.home.featured_sellers_limit'))
            ->with('latestListedAd.location')
            ->get();
    }

    /**
     * @return Collection<int, Location>
     */
    private function places(): Collection
    {
        return Location::query()
            ->active()
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get();
    }
}
