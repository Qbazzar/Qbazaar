<?php

declare(strict_types=1);

namespace App\Actions\PurchaseRequests;

use App\Data\Messaging\ChatMessageDraft;
use App\Enums\ChatMessageKey;
use App\Enums\PurchaseRequestStatus;
use App\Events\PurchaseRequests\PurchaseRequestCancelled;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\Messaging\ConversationMessageWriter;
use App\Services\PurchaseRequests\PurchaseRequestTransitionService;
use Illuminate\Support\Facades\DB;

/**
 * The buyer withdraws a pending request before the seller answers.
 */
class CancelPurchaseRequestAction
{
    public function __construct(
        private readonly PurchaseRequestTransitionService $transitions,
        private readonly ConversationMessageWriter $messages,
    ) {}

    public function __invoke(User $buyer, PurchaseRequest $request): PurchaseRequest
    {
        if ($buyer->id !== $request->buyer_id) {
            throw new DomainException(ErrorCode::PURCHASE_REQUEST_FORBIDDEN);
        }

        return $this->transitions->withLockedPendingRequest($request, function (PurchaseRequest $request) use ($buyer): PurchaseRequest {
            $this->transitions->moveTo($request, PurchaseRequestStatus::CANCELLED, ['cancelled_by' => $buyer->id]);

            $this->messages->append($request->conversation, $buyer, ChatMessageDraft::system(ChatMessageKey::PURCHASE_REQUEST_CANCELLED));

            DB::afterCommit(fn () => PurchaseRequestCancelled::dispatch($request, $request->seller_id));

            return $request;
        });
    }
}
