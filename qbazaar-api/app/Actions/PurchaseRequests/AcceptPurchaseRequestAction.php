<?php

declare(strict_types=1);

namespace App\Actions\PurchaseRequests;

use App\Data\Messaging\ChatMessageDraft;
use App\Enums\ChatMessageKey;
use App\Enums\PurchaseRequestStatus;
use App\Events\PurchaseRequests\PurchaseRequestAccepted;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\Messaging\ConversationMessageWriter;
use App\Services\Offers\OfferTransitionService;
use App\Services\Orders\OrderPlacementService;
use App\Services\PurchaseRequests\PurchaseRequestTransitionService;
use Illuminate\Support\Facades\DB;

/**
 * The seller accepts a pending request. In one transaction, under the ad
 * lock: the order is placed at the request's price and quantity (which
 * reserves the ad), every other pending request and open offer on the ad is
 * closed, and the card flips to accepted. If the order cannot be placed
 * (e.g. the seller is over the commission debt ceiling) nothing changes.
 */
class AcceptPurchaseRequestAction
{
    public function __construct(
        private readonly PurchaseRequestTransitionService $transitions,
        private readonly OfferTransitionService $offers,
        private readonly OrderPlacementService $orders,
        private readonly ConversationMessageWriter $messages,
    ) {}

    public function __invoke(User $seller, PurchaseRequest $request): PurchaseRequest
    {
        if ($seller->id !== $request->seller_id) {
            throw new DomainException(ErrorCode::PURCHASE_REQUEST_FORBIDDEN);
        }

        return $this->transitions->withLockedPendingRequest($request, function (PurchaseRequest $request, Ad $ad) use ($seller): PurchaseRequest {
            $this->transitions->assertAdIsPurchasable($ad);
            $this->transitions->assertQuantityAvailable($ad, $request->quantity);

            $order = $this->orders->placeFromPurchaseRequest($request->id, $request->buyer_id, $ad, $request->unit_price, $request->quantity);

            $this->transitions->moveTo($request, PurchaseRequestStatus::ACCEPTED, ['order_id' => $order->id]);
            $this->transitions->cancelOpenRequestsOnAd($ad->id, exceptRequestId: $request->id);
            $this->offers->expireOpenOffersOnAd($ad->id);

            $this->messages->append($request->conversation, $seller, ChatMessageDraft::system(ChatMessageKey::PURCHASE_REQUEST_ACCEPTED));

            DB::afterCommit(fn () => PurchaseRequestAccepted::dispatch($request, $request->buyer_id));

            return $request;
        });
    }
}
