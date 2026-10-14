<?php

declare(strict_types=1);

namespace App\Data\Admin\AdDeal;

use App\Models\Offer;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Everything the deal panel of the admin ad page shows. A part the viewer may
 * not read is null (or empty with its access flag off) and never queried.
 */
final readonly class AdTransaction
{
    /**
     * @param Collection<int, ConversationRow> $conversations
     * @param Collection<int, Offer> $offers
     */
    public function __construct(
        public DealAccess $access,
        public bool $showDeal,
        public ?User $buyer,
        public BuyerBasis $buyerBasis,
        public ?OrderDeal $deal,
        public Collection $conversations,
        public int $conversationsTotal,
        public Collection $offers,
        public int $offersTotal,
    ) {}
}
