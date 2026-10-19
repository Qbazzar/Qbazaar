<?php

declare(strict_types=1);

namespace App\Services\Admin\Offers;

use App\Enums\OfferStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Models\Ad;
use App\Models\Offer;
use App\Models\Order;
use App\Models\User;
use App\Services\Admin\EntityTimeline;
use App\Services\Admin\TimelineEntry;
use Illuminate\Support\Collection;

/**
 * The data behind the admin offer page: the offer with its ad and parties,
 * the order it became (found through orders_source_unique) and its staff
 * history. A fixed number of queries whatever the offer's past; the history is
 * read inside rescue() so a failing widget shows its error state.
 */
final class OfferDetail
{
    public const int TIMELINE_LIMIT = 8;

    /** Badge tone per order status, as on the admin order page. */
    private const array ORDER_TONES = [
        'created' => 'neutral',
        'awaiting_handover' => 'info',
        'completed' => 'success',
        'cancelled' => 'danger',
        'disputed' => 'warning',
    ];

    public function __construct(
        private readonly EntityTimeline $timeline,
    ) {}

    /**
     * @return array{
     *     offer: Offer,
     *     order: Order|null,
     *     orderTone: string|null,
     *     timeline: Collection<int, TimelineEntry>|null,
     *     canCancel: bool,
     *     canResolveDispute: bool,
     *     canOpenOrder: bool,
     *     canOpenChat: bool,
     *     canOpenAd: bool,
     * }
     */
    public function forShow(Offer $offer, User $viewer): array
    {
        // A removed ad still names the offer, so it is read with the trashed ones.
        $offer->setRelation('ad', Ad::withTrashed()->find($offer->ad_id, ['id', 'title', 'status', 'deleted_at']));
        $offer->load([
            'buyer.avatarMedia',
            'seller.avatarMedia',
            'parentOffer:id,amount,currency,status,created_at',
        ]);

        $order = $offer->status === OfferStatus::ACCEPTED
            ? Order::query()->where('source', OrderSource::OFFER->value)->where('source_id', $offer->id)->first()
            : null;

        return [
            'offer' => $offer,
            'order' => $order,
            'orderTone' => $order !== null ? (self::ORDER_TONES[$order->status->value] ?? 'neutral') : null,
            'timeline' => rescue(fn (): Collection => $this->timeline->forSubject($offer, self::TIMELINE_LIMIT), null),
            // An offer past its window is already closed for the parties; only a live one can be cancelled.
            'canCancel' => $offer->isActive() && $viewer->can('offers.manage'),
            'canResolveDispute' => $order?->status === OrderStatus::DISPUTED && $viewer->can('finance.manage'),
            'canOpenOrder' => $order !== null && $viewer->can('finance.view'),
            'canOpenChat' => $viewer->can('conversations.view'),
            'canOpenAd' => $offer->ad !== null && ! $offer->ad->trashed() && $viewer->can('ads.view'),
        ];
    }
}
