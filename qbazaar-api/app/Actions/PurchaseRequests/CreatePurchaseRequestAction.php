<?php

declare(strict_types=1);

namespace App\Actions\PurchaseRequests;

use App\Actions\Messaging\StartConversationAction;
use App\Data\Messaging\ChatMessageDraft;
use App\Enums\PurchaseRequestStatus;
use App\Events\PurchaseRequests\PurchaseRequestCreated;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\Messaging\ConversationMessageWriter;
use App\Services\PurchaseRequests\PurchaseRequestTransitionService;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The buyer asks to buy the ad at its listed price ("Buy Now"). The request
 * lands in the conversation about the ad (started when needed) as a card the
 * seller accepts or rejects. One pending request per buyer and ad.
 */
class CreatePurchaseRequestAction
{
    public function __construct(
        private readonly StartConversationAction $conversations,
        private readonly PurchaseRequestTransitionService $transitions,
        private readonly ConversationMessageWriter $messages,
    ) {}

    public function __invoke(User $buyer, Ad $ad, int $quantity, ?string $note): PurchaseRequest
    {
        if ($ad->user_id === $buyer->id) {
            throw new DomainException(ErrorCode::PURCHASE_REQUEST_OWN_AD);
        }

        $conversation = $this->conversations->execute($buyer, $ad)['conversation'];

        if (! $conversation->seller->privacySettings()->allow_chat) {
            throw new DomainException(ErrorCode::MSG_CHAT_DISABLED);
        }

        try {
            return $this->transitions->withLockedAd(
                $ad->id,
                fn (Ad $locked): PurchaseRequest => $this->create($locked, $buyer, $conversation, $quantity, $note),
            );
        } catch (UniqueConstraintViolationException) {
            throw new DomainException(ErrorCode::PURCHASE_REQUEST_OPEN_EXISTS);
        }
    }

    private function create(Ad $ad, User $buyer, Conversation $conversation, int $quantity, ?string $note): PurchaseRequest
    {
        $this->transitions->assertAdIsPurchasable($ad);
        $this->transitions->assertQuantityAvailable($ad, $quantity);

        $hasOpenRequest = PurchaseRequest::query()
            ->where('ad_id', $ad->id)
            ->where('buyer_id', $buyer->id)
            ->where('is_open', true)
            ->exists();

        if ($hasOpenRequest) {
            throw new DomainException(ErrorCode::PURCHASE_REQUEST_OPEN_EXISTS);
        }

        $unitPrice = Money::of((string) $ad->price);

        $message = $this->messages->append(
            $conversation,
            $buyer,
            ChatMessageDraft::purchaseRequest(Money::multiply($unitPrice, $quantity), $ad->currency, $quantity, $note),
        );

        $request = new PurchaseRequest;
        $request->forceFill([
            'conversation_id' => $conversation->id,
            'ad_id' => $ad->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $ad->user_id,
            'message_id' => $message->id,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'currency' => $ad->currency,
            'note' => $note,
            'status' => PurchaseRequestStatus::PENDING,
        ])->save();

        $message->setRelation('purchaseRequest', $request);

        DB::afterCommit(fn () => PurchaseRequestCreated::dispatch($request, $request->seller_id));

        return $request;
    }
}
