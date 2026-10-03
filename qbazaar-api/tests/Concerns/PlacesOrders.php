<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Enums\AdStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderTransitionService;

/**
 * Builds orders the way production does: a pending offer accepted by the
 * seller. Needs CreatesAds and seeded reference data.
 */
trait PlacesOrders
{
    protected function pendingOffer(User $seller, User $buyer, ?Ad $ad = null, string $amount = '200.00'): Offer
    {
        $ad ??= $this->makeAd($seller, ['status' => AdStatus::ACTIVE->value]);

        $conversation = Conversation::query()->firstOrCreate([
            'ad_id' => $ad->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
        ]);

        return Offer::factory()->pending()->create([
            'conversation_id' => $conversation->id,
            'ad_id' => $ad->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'amount' => $amount,
        ]);
    }

    protected function acceptOffer(Offer $offer): Order
    {
        $this->actingAs($offer->seller, 'sanctum')
            ->postJson('/api/v1/offers/' . $offer->id . '/accept')
            ->assertOk();

        return Order::query()->where('source_id', $offer->id)->sole();
    }

    protected function placeOrder(User $seller, User $buyer, string $amount = '200.00', ?Ad $ad = null): Order
    {
        return $this->acceptOffer($this->pendingOffer($seller, $buyer, $ad, $amount));
    }

    protected function orderAwaitingHandover(User $seller, User $buyer, string $amount = '200.00'): Order
    {
        $order = $this->placeOrder($seller, $buyer, $amount);

        app(OrderTransitionService::class)->awaitHandover($order, PaymentMethod::CASH);

        expect($order->status)->toBe(OrderStatus::AWAITING_HANDOVER);

        return $order;
    }
}
