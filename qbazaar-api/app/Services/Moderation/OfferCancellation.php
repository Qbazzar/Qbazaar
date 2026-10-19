<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Data\Moderation\SanctionReasonInput;
use App\Enums\OfferStatus;
use App\Events\Offers\OfferExpired;
use App\Models\Offer;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Offers\OfferTransitionService;
use Illuminate\Support\Facades\DB;

/**
 * Staff close an open offer from the panel. It ends as EXPIRED ("closed by
 * the platform"), through the same locked transition the expiry sweep uses,
 * so the chat clients refresh the offer card exactly as they do for a sweep.
 * No money moves: an accepted offer has an order, and its disputes are ruled
 * through the finance dispute flow.
 */
class OfferCancellation
{
    public const string AUDIT_EVENT = 'admin.offers.cancel';

    public function __construct(
        private readonly OfferTransitionService $transitions,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function cancel(User $staff, Offer $offer, SanctionReasonInput $input): Offer
    {
        return $this->transitions->withLockedOffer($offer, function (Offer $locked) use ($staff, $input): Offer {
            $this->transitions->moveTo($locked, OfferStatus::EXPIRED);

            $this->audit->record($staff, self::AUDIT_EVENT, $locked, [
                ...$input->toAudit(),
                'from' => OfferStatus::PENDING->value,
            ]);

            DB::afterCommit(fn () => OfferExpired::dispatch($locked, $locked->buyer_id));

            return $locked;
        });
    }
}
