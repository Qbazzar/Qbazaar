<?php

declare(strict_types=1);

namespace App\Actions\PurchaseRequests;

use App\Data\Messaging\ChatMessageDraft;
use App\Enums\ChatMessageKey;
use App\Events\PurchaseRequests\PurchaseRequestUpdated;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\Messaging\ConversationMessageWriter;
use App\Services\PurchaseRequests\PurchaseRequestTransitionService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * The buyer changes the quantity or the note of a pending request. The price
 * is re-read from the ad, so the seller always answers the current price.
 * The card keeps its bubble; a system line records the change.
 */
class UpdatePurchaseRequestAction
{
    public function __construct(
        private readonly PurchaseRequestTransitionService $transitions,
        private readonly ConversationMessageWriter $messages,
    ) {}

    public function __invoke(User $buyer, PurchaseRequest $request, int $quantity, ?string $note): PurchaseRequest
    {
        if ($buyer->id !== $request->buyer_id) {
            throw new DomainException(ErrorCode::PURCHASE_REQUEST_FORBIDDEN);
        }

        return $this->transitions->withLockedPendingRequest($request, function (PurchaseRequest $request, Ad $ad) use ($buyer, $quantity, $note): PurchaseRequest {
            $this->transitions->assertAdIsPurchasable($ad);
            $this->transitions->assertQuantityAvailable($ad, $quantity);

            $request->forceFill([
                'unit_price' => Money::of((string) $ad->price),
                'quantity' => $quantity,
                'note' => $note,
            ])->save();

            $params = ['amount' => $request->total(), 'currency' => $request->currency, 'quantity' => $quantity];

            if ($note !== null && $note !== '') {
                $params['note'] = $note;
            }

            $this->messages->append($request->conversation, $buyer, ChatMessageDraft::system(ChatMessageKey::PURCHASE_REQUEST_UPDATED, $params));

            DB::afterCommit(fn () => PurchaseRequestUpdated::dispatch($request, $request->seller_id));

            return $request;
        });
    }
}
