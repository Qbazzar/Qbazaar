<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Data\Catalog\HomeFeed;
use App\Enums\AccountType;
use App\Enums\HomeSection;
use App\Enums\HomeSectionMode;
use App\Enums\UserStatus;
use App\Models\Ad;
use App\Models\Location;
use App\Models\User;
use App\Services\Business\FeaturedCompanies;
use App\Services\Catalog\CategoryTree;
use App\Services\Catalog\HomeSections;
use App\Services\Catalog\LocationAdCounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Everything the home screen shows, gathered in one pass.
 *
 * Staff curate the rails in the admin panel. A section in manual mode shows
 * its hand-picked ads that are still listed, in staff order; in automatic
 * mode (or when none of its picks is listed any more) it follows the
 * built-in rule: recommended = paid promotions first, then the newest ads of
 * the last 24 hours (topped up with the most viewed); best selling = most
 * favourited. Featured sellers = the companies staff feature, in their
 * order, then the business accounts with the most live ads.
 */
class GetHomeFeedAction
{
    private const AD_RELATIONS = ['category', 'location', 'primaryImage'];

    public function __construct(
        private readonly CategoryTree $categoryTree,
        private readonly LocationAdCounts $placeCounts,
        private readonly HomeSections $sections,
        private readonly FeaturedCompanies $featuredCompanies,
    ) {}

    public function execute(): HomeFeed
    {
        $modes = $this->sections->modes();
        $featured = $this->featuredCompanies->listed();

        return new HomeFeed(
            categories: $this->categoryTree->roots(),
            recommended: $this->curated(HomeSection::RECOMMENDED, $modes) ?? $this->recommended(),
            featuredSellers: $this->featuredSellers($featured),
            bestSelling: $this->curated(HomeSection::BEST_SELLING, $modes) ?? $this->bestSelling(),
            places: $this->places(),
            placeCounts: $this->placeCounts->all(),
            featuredSellerIds: array_fill_keys($featured->modelKeys(), true),
        );
    }

    /**
     * The section's listed picks when staff run it by hand; null hands the
     * rail to the automatic rule, so it never goes empty because every pick
     * was sold or expired.
     *
     * @param array<string, HomeSectionMode> $modes
     * @return Collection<int, Ad>|null
     */
    private function curated(HomeSection $section, array $modes): ?Collection
    {
        if ($modes[$section->value] !== HomeSectionMode::MANUAL) {
            return null;
        }

        $ads = $this->sections->curatedAds($section);

        return $ads->isEmpty() ? null : $ads->load(self::AD_RELATIONS);
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
     * The companies staff feature, in their order, then the busiest business
     * sellers to fill the rail. Relations are loaded once for the whole rail.
     *
     * @param Collection<int, User> $featured
     * @return Collection<int, User>
     */
    private function featuredSellers(Collection $featured): Collection
    {
        $limit = (int) config('qbazaar.home.featured_sellers_limit');
        $rail = $featured->take($limit)->values();
        $remaining = $limit - $rail->count();

        if ($remaining > 0) {
            $rail = $rail->concat(User::query()
                ->where('account_type', AccountType::BUSINESS->value)
                ->where('status', UserStatus::ACTIVE->value)
                ->where('active_ads_count', '>', 0)
                ->whereKeyNot($rail->modelKeys())
                ->orderByDesc('active_ads_count')
                ->orderBy('id')
                ->limit($remaining)
                ->get());
        }

        return $rail->load('latestListedAd.location');
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
