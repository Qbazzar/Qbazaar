<?php

declare(strict_types=1);

namespace App\Actions\PurchaseRequests;

use App\Data\Messaging\ChatMessageDraft;
use App\Enums\ChatMessageKey;
use App\Enums\PurchaseRequestStatus;
use App\Events\PurchaseRequests\PurchaseRequestRejected;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\Messaging\ConversationMessageWriter;
use App\Services\PurchaseRequests\PurchaseRequestTransitionService;
use Illuminate\Support\Facades\DB;

/**
 * The seller declines a pending request.
 */
class RejectPurchaseRequestAction
{
    public function __construct(
        private readonly PurchaseRequestTransitionService $transitions,
        private readonly ConversationMessageWriter $messages,
    ) {}

    public function __invoke(User $seller, PurchaseRequest $request): PurchaseRequest
    {
        if ($seller->id !== $request->seller_id) {
            throw new DomainException(ErrorCode::PURCHASE_REQUEST_FORBIDDEN);
        }

        return $this->transitions->withLockedPendingRequest($request, function (PurchaseRequest $request) use ($seller): PurchaseRequest {
            $this->transitions->moveTo($request, PurchaseRequestStatus::REJECTED);

            $this->messages->append($request->conversation, $seller, ChatMessageDraft::system(ChatMessageKey::PURCHASE_REQUEST_REJECTED));

            DB::afterCommit(fn () => PurchaseRequestRejected::dispatch($request, $request->buyer_id));

            return $request;
        });
    }
}
