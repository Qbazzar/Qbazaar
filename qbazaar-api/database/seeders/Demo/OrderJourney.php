<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Actions\Orders\CancelOrderAction;
use App\Actions\Orders\CheckoutOrderAction;
use App\Actions\Orders\ConfirmOrderHandoverAction;
use App\Actions\Orders\ReportOrderProblemAction;
use App\Actions\Orders\ResolveOrderDisputeAction;
use App\Actions\Reviews\CreateReviewAction;
use App\Data\Orders\CheckoutDetails;
use App\Enums\DisputeResolution;
use App\Enums\Fulfillment;
use App\Enums\OrderSource;
use App\Enums\PaymentMethod;
use App\Models\Ad;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\Demo\Catalog\Phrases;
use Illuminate\Support\Carbon;

/**
 * Takes an order that was just placed as far as the scenario asks, through
 * the same actions the app and the admin panel call: checkout, handover,
 * cancellation, the buyer's report and the admin's ruling. Deals from
 * accepted offers and from "Buy now" requests share it.
 */
final class OrderJourney
{
    private const float REVIEW_SHARE = 0.8;

    /** Stays inside the default report window so the buyer can still report. */
    private const int MAX_HOURS_TO_REPORT = 20;

    public function __construct(
        private readonly CheckoutOrderAction $checkout,
        private readonly ConfirmOrderHandoverAction $confirmHandover,
        private readonly CancelOrderAction $cancelOrder,
        private readonly ReportOrderProblemAction $reportProblem,
        private readonly ResolveOrderDisputeAction $resolveDispute,
        private readonly CreateReviewAction $createReview,
    ) {}

    public function run(DemoContext $context, Order $order, Ad $ad, User $buyer, OrderOutcome $outcome, Carbon $placedAt, Fulfillment $fulfillment): void
    {
        if ($outcome === OrderOutcome::CREATED) {
            return;
        }

        $context->clock->at($placedAt->copy()->addHour(), fn () => ($this->checkout)($buyer, $order, $this->checkoutDetails($context, $order, $ad, $buyer, $fulfillment)));

        $handoverAt = $placedAt->copy()->addDays($context->random->int(1, 3));

        match (true) {
            $outcome === OrderOutcome::AWAITING_HANDOVER => null,
            $outcome === OrderOutcome::CANCELLED => $context->clock->at($handoverAt, fn () => ($this->cancelOrder)($buyer, $order, $context->random->pick(Phrases::CANCELLATION_REASONS[$buyer->language->value]))),
            default => $this->handOver($context, $order, $ad, $buyer, $outcome, $handoverAt),
        };
    }

    private function handOver(DemoContext $context, Order $order, Ad $ad, User $buyer, OrderOutcome $outcome, Carbon $handoverAt): void
    {
        $context->clock->at($handoverAt, fn () => ($this->confirmHandover)($ad->user, $order));

        if ($outcome->isDispute()) {
            $this->dispute($context, $order, $buyer, $outcome, $handoverAt);

            return;
        }

        // Review eligibility is granted by an accepted offer only; "Buy now"
        // buyers cannot review yet, so their orders are left without one.
        if ($order->source === OrderSource::OFFER && $context->random->chance(self::REVIEW_SHARE)) {
            $this->review($context, $ad, $buyer, $handoverAt);
        }
    }

    private function dispute(DemoContext $context, Order $order, User $buyer, OrderOutcome $outcome, Carbon $handoverAt): void
    {
        $reportedAt = $handoverAt->copy()->addHours($context->random->int(1, self::MAX_HOURS_TO_REPORT));
        $reason = $context->random->pick(Phrases::DISPUTE_REASONS[$buyer->language->value]);
        $context->clock->at($reportedAt, fn () => ($this->reportProblem)($buyer, $order, $reason));

        if ($outcome === OrderOutcome::DISPUTE_OPEN) {
            return;
        }

        $ruling = $outcome === OrderOutcome::DISPUTE_UPHELD ? DisputeResolution::CANCELLED : DisputeResolution::COMPLETED;
        $note = $context->random->pick(Phrases::RULING_NOTES[$ruling->value]);
        $context->clock->at(
            $reportedAt->copy()->addHours($context->random->int(6, 40)),
            fn () => ($this->resolveDispute)($context->superAdmin(), $order, $ruling, $note),
        );
    }

    private function review(DemoContext $context, Ad $ad, User $buyer, Carbon $handoverAt): void
    {
        $rating = $context->random->pick([5, 5, 5, 4, 4, 3, 2]);
        $context->clock->at(
            $handoverAt->copy()->addHours($context->random->int(2, 30)),
            fn () => $this->createReview->execute($buyer, $ad, $rating, Phrases::REVIEWS[$rating][$buyer->language->value]),
        );
    }

    private function checkoutDetails(DemoContext $context, Order $order, Ad $ad, User $buyer, Fulfillment $fulfillment): CheckoutDetails
    {
        $address = $fulfillment === Fulfillment::DELIVERY ? [
            'full_name' => $buyer->full_name,
            'phone' => $buyer->phone,
            'street' => 'Street ' . $context->random->int(100, 999),
            'house_number' => (string) $context->random->int(1, 90),
            'city' => 'Doha',
            'location_id' => (string) $ad->location_id,
        ] : null;

        return new CheckoutDetails($fulfillment, PaymentMethod::CASH, $order->quantity, inlineAddress: $address);
    }
}
