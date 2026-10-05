<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Steps;

use App\Actions\PurchaseRequests\AcceptPurchaseRequestAction;
use App\Actions\PurchaseRequests\CancelPurchaseRequestAction;
use App\Actions\PurchaseRequests\CreatePurchaseRequestAction;
use App\Actions\PurchaseRequests\RejectPurchaseRequestAction;
use App\Enums\AdShipping;
use App\Enums\Fulfillment;
use App\Enums\PurchaseRequestStatus;
use App\Models\Ad;
use App\Models\Order;
use App\Models\PurchaseRequest;
use App\Models\User;
use Database\Seeders\Demo\Catalog\Phrases;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\DemoFinance;
use Database\Seeders\Demo\OrderJourney;
use Database\Seeders\Demo\OrderOutcome;
use Illuminate\Support\Carbon;

/**
 * "Buy now" requests on the listings the haggling left alone. Accepting one
 * places the order through OrderPlacementService; a request whose order is
 * completed turns "paid" through the production listener.
 */
final class SeedPurchaseRequests
{
    /** Every final state appears within the first pass. */
    private const array PLAN = [
        PurchaseRequestStatus::PAID, PurchaseRequestStatus::PENDING, PurchaseRequestStatus::ACCEPTED,
        PurchaseRequestStatus::REJECTED, PurchaseRequestStatus::CANCELLED, PurchaseRequestStatus::PAID,
        PurchaseRequestStatus::PENDING, PurchaseRequestStatus::ACCEPTED,
    ];

    private const float REQUESTS_PER_AD = 0.06;

    public function __construct(
        private readonly CreatePurchaseRequestAction $create,
        private readonly AcceptPurchaseRequestAction $accept,
        private readonly RejectPurchaseRequestAction $reject,
        private readonly CancelPurchaseRequestAction $cancel,
        private readonly DemoFinance $finance,
        private readonly OrderJourney $journey,
    ) {}

    public function run(DemoContext $context): void
    {
        $count = max(count(self::PLAN), (int) round($context->options->ads * self::REQUESTS_PER_AD));

        for ($index = 0; $index < $count; $index++) {
            $ad = array_pop($context->spareAds);

            if ($ad === null) {
                return;
            }

            $this->play($context, $ad, self::PLAN[$index % count(self::PLAN)], $index);
        }
    }

    private function play(DemoContext $context, Ad $ad, PurchaseRequestStatus $target, int $index): void
    {
        $buyer = $context->memberOtherThan($ad->user_id);
        $seller = $ad->user;
        $requestedAt = $context->clock->daysAgo($context->random->int(1, 12), $context->random->int(0, 600));
        $answeredAt = $requestedAt->copy()->addHours($context->random->int(1, 8));
        $note = $context->random->chance(0.6) ? $context->random->pick(Phrases::PURCHASE_NOTES[$buyer->language->value]) : null;

        $request = $context->clock->at($requestedAt, fn (): PurchaseRequest => ($this->create)($buyer, $ad, 1, $note));

        match ($target) {
            PurchaseRequestStatus::PENDING => null,
            PurchaseRequestStatus::REJECTED => $context->clock->at($answeredAt, fn () => ($this->reject)($seller, $request)),
            PurchaseRequestStatus::CANCELLED => $context->clock->at($answeredAt, fn () => ($this->cancel)($buyer, $request)),
            PurchaseRequestStatus::ACCEPTED => $this->acceptAndPlace($context, $request, $ad, $buyer, $index % 2 === 0 ? OrderOutcome::AWAITING_HANDOVER : OrderOutcome::CREATED, $answeredAt),
            PurchaseRequestStatus::PAID => $this->acceptAndPlace($context, $request, $ad, $buyer, OrderOutcome::COMPLETED, $answeredAt),
        };
    }

    private function acceptAndPlace(DemoContext $context, PurchaseRequest $request, Ad $ad, User $buyer, OrderOutcome $outcome, Carbon $at): void
    {
        $this->finance->ensureCanTakeOrders($context, $ad->user, $at);

        $accepted = $context->clock->at($at, fn (): PurchaseRequest => ($this->accept)($ad->user, $request));
        $fulfillment = $ad->shipping === AdShipping::DELIVERY ? Fulfillment::DELIVERY : Fulfillment::PICKUP;

        $this->journey->run($context, Order::query()->findOrFail($accepted->order_id), $ad, $buyer, $outcome, $at, $fulfillment);
    }
}
