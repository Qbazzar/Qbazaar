<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Content;

/**
 * What a content refresh did, or in dry-run mode what it would do.
 */
final class RefreshReport
{
    public int $accountsMatched = 0;

    public int $accountsMissing = 0;

    public int $accountsUpdated = 0;

    public int $placeholderAdsRemoved = 0;

    public int $adsKeptForHistory = 0;

    public int $adsKeptPhotosSwapped = 0;

    public int $designAdsCreated = 0;

    public int $designAdsKept = 0;

    public int $designAdsSkipped = 0;

    public int $adsExtended = 0;

    public int $adsRevived = 0;

    public int $homeAdsCurated = 0;

    public int $companiesFeatured = 0;

    public int $adsReindexed = 0;

    /**
     * @return list<array{string, int}>
     */
    public function rows(): array
    {
        return [
            ['Demo accounts matched to a design seller', $this->accountsMatched],
            ['Design sellers without a demo account', $this->accountsMissing],
            ['Demo accounts updated (name, avatar, profile, cover)', $this->accountsUpdated],
            ['Old placeholder ads removed', $this->placeholderAdsRemoved],
            ['Old ads kept (orders, offers, chats or other users depend on them)', $this->adsKeptForHistory],
            ['  of which got design photos', $this->adsKeptPhotosSwapped],
            ['Design ads created', $this->designAdsCreated],
            ['Design ads already there (refreshed in place)', $this->designAdsKept],
            ['Design ads skipped (no demo owner or category)', $this->designAdsSkipped],
            ['Live demo ads given a fresh expiry', $this->adsExtended],
            ['Expired demo ads brought back to live', $this->adsRevived],
            ['Ads put in the home "Best selling" rail', $this->homeAdsCurated],
            ['Companies featured on the home page', $this->companiesFeatured],
            ['Ads sent to the search index', $this->adsReindexed],
        ];
    }
}
