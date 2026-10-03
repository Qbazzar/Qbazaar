<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerReference;
use App\Enums\LedgerReferenceType;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\User;
use App\Services\Ledger\LedgerRecipes;
use App\Services\Orders\OrderTransitionService;
use Illuminate\Support\Str;

/**
 * The one place where the demo meets features still being built on other
 * branches (purchase requests, checkout, settlements and withdrawals,
 * disputes, promotions). Each method does what main supports today; when a
 * feature lands, only its method here changes and the steps keep working.
 */
final class UpcomingFeatures
{
    public function __construct(
        private readonly OrderTransitionService $orders,
        private readonly LedgerRecipes $recipes,
    ) {}

    /**
     * TODO(purchase-requests): seed "Buy now" requests (accepted, declined,
     * expired) once BE-14.34 ships its model and actions.
     */
    public function seedPurchaseRequests(DemoContext $context): void {}

    /**
     * TODO(checkout): go through the checkout action (address, delivery,
     * payment method) once BE-14.37 ships; today the order moves directly.
     */
    public function checkout(Order $order): Order
    {
        return $this->orders->awaitHandover($order, PaymentMethod::CASH);
    }

    /**
     * TODO(disputes): open it through the disputes flow (reason, evidence,
     * admin ruling) once it ships; today only the status moves.
     */
    public function openDispute(Order $order): Order
    {
        return $this->orders->dispute($order);
    }

    /**
     * TODO(settlements): create and approve a settlement record through the
     * settlements service; until then the posting references a fresh id.
     */
    public function settleCommissionByBankTransfer(User $seller, string $amount, User $admin): void
    {
        $this->recipes->settleCommissionByBankTransfer($seller->id, $amount, $this->reference(LedgerReferenceType::SETTLEMENT), LedgerActor::admin($admin));
    }

    /**
     * TODO(settlements): net through the settlements service, as above.
     */
    public function settleCommissionFromWallet(User $seller, string $amount, User $admin): void
    {
        $this->recipes->settleCommissionFromWallet($seller->id, $amount, $this->reference(LedgerReferenceType::SETTLEMENT), LedgerActor::admin($admin));
    }

    public function creditWallet(User $member, string $amount, string $memo, User $admin): void
    {
        $this->recipes->adjustWallet($member->id, $amount, $memo, $this->reference(LedgerReferenceType::ADJUSTMENT), LedgerActor::admin($admin));
    }

    /**
     * TODO(withdrawals): request, pay and reject withdrawals through the
     * withdrawals service once it ships.
     */
    public function seedWithdrawals(DemoContext $context): void {}

    /**
     * TODO(promotions): buy promotions from wallets and by bank transfer once
     * they ship; today the admin "featured" flag is the only promotion.
     */
    public function seedPromotions(DemoContext $context): void {}

    private function reference(LedgerReferenceType $type): LedgerReference
    {
        return new LedgerReference($type, (string) Str::ulid());
    }
}
