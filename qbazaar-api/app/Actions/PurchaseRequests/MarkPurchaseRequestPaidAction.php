<?php

declare(strict_types=1);

namespace App\Actions\PurchaseRequests;

use App\Data\Messaging\ChatMessageDraft;
use App\Enums\ChatMessageKey;
use App\Enums\PurchaseRequestStatus;
use App\Events\PurchaseRequests\PurchaseRequestPaid;
use App\Models\Order;
use App\Models\PurchaseRequest;
use App\Services\Messaging\ConversationMessageWriter;
use App\Services\PurchaseRequests\PurchaseRequestTransitionService;
use Illuminate\Support\Facades\DB;

/**
 * Flips the card of a completed order's request to paid. Runs after the
 * order commits; only an accepted request moves, so a retry changes nothing.
 */
class MarkPurchaseRequestPaidAction
{
    public function __construct(
        private readonly PurchaseRequestTransitionService $transitions,
        private readonly ConversationMessageWriter $messages,
    ) {}

    public function __invoke(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            /** @var PurchaseRequest|null $request */
            $request = PurchaseRequest::query()->whereKey($order->source_id)->lockForUpdate()->first();

            if ($request === null || $request->status !== PurchaseRequestStatus::ACCEPTED) {
                return;
            }

            $this->transitions->moveTo($request, PurchaseRequestStatus::PAID);

            $request->loadMissing(['conversation', 'seller']);
            $this->messages->append($request->conversation, $request->seller, ChatMessageDraft::system(ChatMessageKey::PURCHASE_REQUEST_PAID));

            DB::afterCommit(fn () => PurchaseRequestPaid::dispatch($request, $request->buyer_id));
        });
    }
}
