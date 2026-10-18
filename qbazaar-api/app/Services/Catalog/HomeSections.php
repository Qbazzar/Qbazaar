<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Data\Catalog\HomeSectionPanel;
use App\Enums\HomeSection;
use App\Enums\HomeSectionMode;
use App\Models\Ad;
use App\Models\HomeSectionItem;
use App\Models\HomeSectionSetting;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads the staff curation of the home rails: each section's mode and its
 * hand-picked ads. The home feed is rebuilt by the catalog warmer, so these
 * small indexed reads run once per rebuild, never per visitor. Staff change
 * the curation through {@see HomeSectionCurator}.
 */
class HomeSections
{
    /**
     * Every section's mode; a section without a stored row is automatic.
     *
     * @return array<string, HomeSectionMode> keyed by section value
     */
    public function modes(): array
    {
        $stored = HomeSectionSetting::query()->get()->keyBy('section');
        $modes = [];

        foreach (HomeSection::cases() as $section) {
            $setting = $stored->get($section->value);
            $modes[$section->value] = $setting instanceof HomeSectionSetting ? $setting->mode : HomeSectionMode::AUTO;
        }

        return $modes;
    }

    /**
     * The section's picks that are still publicly listed, in staff order.
     * Two queries: the ordered picks (home_section_items_order_idx) and the
     * live ads among them by primary key.
     *
     * @return Collection<int, Ad>
     */
    public function curatedAds(HomeSection $section): Collection
    {
        /** @var list<string> $order */
        $order = HomeSectionItem::query()
            ->where('section', $section->value)
            ->orderBy('position')
            ->orderBy('id')
            ->limit($section->limit())
            ->pluck('ad_id')
            ->all();

        if ($order === []) {
            return new Collection;
        }

        $live = Ad::query()->publiclyListed()->whereKey($order)->get()->keyBy('id');
        $ads = [];

        foreach ($order as $adId) {
            $ad = $live->get($adId);

            if ($ad instanceof Ad) {
                $ads[] = $ad;
            }
        }

        return new Collection($ads);
    }

    /**
     * Every section for the admin screen: mode, picks and capacity. Four
     * queries for all sections together, whatever the number of picks.
     *
     * @return list<HomeSectionPanel>
     */
    public function panels(): array
    {
        $modes = $this->modes();
        $items = $this->itemsForAdmin();
        $panels = [];

        foreach (HomeSection::cases() as $section) {
            $panels[] = new HomeSectionPanel($section, $modes[$section->value], $items[$section->value], $section->limit());
        }

        return $panels;
    }

    /**
     * Every section's picks with their ads, whatever the ad's status, for the
     * admin screen. Three queries for all sections together.
     *
     * @return array<string, Collection<int, HomeSectionItem>> keyed by section value
     */
    public function itemsForAdmin(): array
    {
        $items = HomeSectionItem::query()
            ->whereIn('section', array_column(HomeSection::cases(), 'value'))
            ->orderBy('position')
            ->orderBy('id')
            ->with('ad.primaryImage')
            ->get()
            ->groupBy('section');

        $grouped = [];

        foreach (HomeSection::cases() as $section) {
            $grouped[$section->value] = new Collection($items->get($section->value)?->all() ?? []);
        }

        return $grouped;
    }
}
