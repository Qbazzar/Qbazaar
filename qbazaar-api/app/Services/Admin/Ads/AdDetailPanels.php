<?php

declare(strict_types=1);

namespace App\Services\Admin\Ads;

use App\Enums\ReportTarget;
use App\Models\Ad;
use App\Models\AdPromotion;
use App\Models\User;
use App\Services\Admin\Reports\TargetReports;
use App\Services\Admin\Reports\TargetReportSummary;
use App\Services\Admin\TimelineEntry;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * The data behind the admin ad review and edit pages. Related lists are
 * bounded, counts come from counter columns or one withCount, and every side
 * widget is read inside rescue(): a failing one comes back null and renders
 * its error state instead of failing the page.
 */
final class AdDetailPanels
{
    public const RELATED_LIMIT = 5;

    public function __construct(
        private readonly TargetReports $reports,
        private readonly AdStatusHistory $history,
    ) {}

    /**
     * @return array{
     *     ad: Ad,
     *     otherAds: EloquentCollection<int, Ad>|null,
     *     reports: TargetReportSummary|null,
     *     promotions: EloquentCollection<int, AdPromotion>|null,
     *     history: Collection<int, TimelineEntry>|null,
     * }
     */
    public function forShow(Ad $ad, User $viewer): array
    {
        $ad->load([
            // The seller's ad count sizes the "other ads" link without a query of its own.
            'user' => fn (BelongsTo $seller) => $seller->withCount('ads'),
            'user.avatarMedia',
            'category',
            'location',
            'media',
        ])->loadCount(['conversations', 'promotions']);

        return [
            'ad' => $ad,
            'otherAds' => rescue(fn (): EloquentCollection => $this->otherAdsBySeller($ad), null),
            // Not read at all for staff who cannot open reports; the view hides the card for them.
            'reports' => $viewer->can('reports.view')
                ? rescue(fn (): TargetReportSummary => $this->reports->for(ReportTarget::AD, $ad->id), null)
                : null,
            'promotions' => rescue(fn (): EloquentCollection => $this->latestPromotions($ad), null),
            'history' => rescue(fn (): Collection => $this->history->forAd($ad), null),
        ];
    }

    /**
     * @return array{ad: Ad, history: Collection<int, TimelineEntry>|null}
     */
    public function forEdit(Ad $ad): array
    {
        $ad->load(['user.avatarMedia', 'media']);

        return [
            'ad' => $ad,
            'history' => rescue(fn (): Collection => $this->history->forAd($ad, withEdits: true), null),
        ];
    }

    /**
     * @return EloquentCollection<int, Ad>
     */
    private function otherAdsBySeller(Ad $ad): EloquentCollection
    {
        return Ad::query()
            ->where('user_id', $ad->user_id)
            ->whereKeyNot($ad->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::RELATED_LIMIT)
            ->get(['id', 'user_id', 'title', 'price', 'price_type', 'currency', 'status', 'created_at']);
    }

    /**
     * @return EloquentCollection<int, AdPromotion>
     */
    private function latestPromotions(Ad $ad): EloquentCollection
    {
        return $ad->promotions()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::RELATED_LIMIT)
            ->get();
    }
}
