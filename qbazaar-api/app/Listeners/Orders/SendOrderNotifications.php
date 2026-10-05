<?php

declare(strict_types=1);

namespace App\Listeners\Orders;

use App\Enums\OfferParty;
use App\Enums\OrderNotice;
use App\Enums\OrderSource;
use App\Events\Orders\OrderAwaitingHandover;
use App\Events\Orders\OrderCancelled;
use App\Events\Orders\OrderCompleted;
use App\Events\Orders\OrderCreated;
use App\Events\Orders\OrderDisputed;
use App\Models\Offer;
use App\Models\Order;
use App\Models\User;
use App\Notifications\Orders\OrderActivityNotification;
use App\Services\Payments\PaymentGateways;
use App\Support\Money;

/**
 * Fans order events out to the buyer and the seller, never to the one who
 * acted: checkout is the buyer's, the cash handover and the dispute window
 * are the seller's and the buyer's per the owner's rules, and an admin
 * ruling concerns both sides. The notifications themselves are queued.
 */
class SendOrderNotifications
{
    public function __construct(private readonly PaymentGateways $gateways) {}

    public function handle(
        OrderCreated|OrderAwaitingHandover|OrderCompleted|OrderCancelled|OrderDisputed $event,
    ): void {
        $order = $event->order;
        $notices = match (true) {
            $event instanceof OrderCreated => [[OrderNotice::CREATED, [$this->recipientOfNewOrder($order)]]],
            $event instanceof OrderAwaitingHandover => [[OrderNotice::AWAITING_HANDOVER, [$order->seller_id]]],
            $event instanceof OrderDisputed => [[OrderNotice::DISPUTE_OPENED, [$order->seller_id]]],
            $event instanceof OrderCompleted => $this->completionNotices($order),
            $event instanceof OrderCancelled => [$this->cancellationNotice($order)],
        };

        $this->send($order, $notices);
    }

    /**
     * The order is placed by whoever accepted: the seller for a purchase
     * request, the party the offer was made to for an offer. The other side
     * is told.
     */
    private function recipientOfNewOrder(Order $order): ?string
    {
        if ($order->source === OrderSource::PURCHASE_REQUEST) {
            return $order->buyer_id;
        }

        $proposer = Offer::query()->whereKey($order->source_id)->value('proposed_by');

        return $proposer === OfferParty::SELLER ? $order->seller_id : $order->buyer_id;
    }

    /**
     * A ruling re-fires the completion of an order that was already
     * completed once, so it must not repeat the handover or commission
     * notices: the buyer and the seller are told only about the ruling.
     *
     * @return list<array{OrderNotice, list<string|null>}>
     */
    private function completionNotices(Order $order): array
    {
        if ($order->disputed_at !== null) {
            return [[OrderNotice::DISPUTE_RESOLVED, [$order->buyer_id, $order->seller_id]]];
        }

        $notices = [[OrderNotice::HANDED_OVER, [$order->buyer_id]]];

        // Cash changed hands outside the platform, so the commission is now a debt.
        $chargedCommission = ! $this->gateways->for($order->payment_method)->holdsFundsInEscrow()
            && Money::isPositive($order->commission_amount);

        if ($chargedCommission) {
            $notices[] = [OrderNotice::COMMISSION_DUE, [$order->seller_id]];
        }

        return $notices;
    }

    /**
     * @return array{OrderNotice, list<string|null>}
     */
    private function cancellationNotice(Order $order): array
    {
        $participants = [$order->buyer_id, $order->seller_id];

        if ($order->disputed_at !== null) {
            return [OrderNotice::DISPUTE_RESOLVED, $participants];
        }

        return [OrderNotice::CANCELLED, array_values(array_filter(
            $participants,
            fn (?string $userId): bool => $userId !== $order->cancelled_by,
        ))];
    }

    /**
     * @param list<array{OrderNotice, list<string|null>}> $notices
     */
    private function send(Order $order, array $notices): void
    {
        $recipientIds = array_values(array_unique(array_filter(
            array_merge(...array_column($notices, 1)),
            fn (?string $userId): bool => $userId !== null,
        )));

        if ($recipientIds === []) {
            return;
        }

        $recipients = User::query()->whereKey($recipientIds)->get()->keyBy('id');

        foreach ($notices as [$notice, $userIds]) {
            foreach ($userIds as $userId) {
                $recipients->get($userId)?->notify(new OrderActivityNotification($notice, $order));
            }
        }
    }
}
