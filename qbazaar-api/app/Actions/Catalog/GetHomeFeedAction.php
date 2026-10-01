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
use Illuminate\Database\Eloquent\Collection;

/**
 * Everything the home screen shows, gathered in one pass.
 *
 * Until the admin can curate the sections (AD-17.1, AD-17.2) they run in
 * automatic mode: recommended = most viewed among recent ads, best selling =
 * most favourited, featured sellers = business accounts with the most live ads.
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
     * @return Collection<int, Ad>
     */
    private function recommended(): Collection
    {
        $windowDays = (int) config('qbazaar.home.recommended_window_days');

        return Ad::query()
            ->publiclyListed()
            ->where('published_at', '>=', now()->subDays($windowDays))
            ->orderByDesc('views_count')
            ->orderByDesc('published_at')
            ->limit((int) config('qbazaar.home.recommended_limit'))
            ->with(self::AD_RELATIONS)
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
            ->get();
    }

    /**
     * @return Collection<int, Location>
     */
    private function places(): Collection
    {
        return Location::query()
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get();
    }
}
