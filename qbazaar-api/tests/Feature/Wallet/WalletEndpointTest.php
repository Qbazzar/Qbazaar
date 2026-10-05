<?php

declare(strict_types=1);

use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerReference;
use App\Enums\LedgerReferenceType;
use App\Models\Order;
use App\Models\User;
use App\Services\Ledger\LedgerRecipes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seller = User::factory()->create();
    $this->recipes = app(LedgerRecipes::class);
    config(['qbazaar.commission.debt_ceiling' => '50.00']);
});

function sellerOrder(User $seller, string $commission): Order
{
    return Order::factory()->create(['ad_id' => null, 'seller_id' => $seller->id, 'commission_amount' => $commission]);
}

function walletRef(LedgerReferenceType $type): LedgerReference
{
    return new LedgerReference($type, (string) Str::ulid());
}

it('shows an empty wallet for a new user', function (): void {
    $this->actingAs($this->seller, 'sanctum')->getJson('/api/v1/account/wallet')
        ->assertOk()
        ->assertExactJson(['success' => true, 'data' => [
            'currency' => 'QAR',
            'available_balance' => '0.00',
            'withdrawable_balance' => '0.00',
            'commission_debt' => '0.00',
            'debt_ceiling' => '50.00',
            'can_accept_orders' => true,
        ]]);
});

it('reports the balance and the commission debt from the ledger', function (): void {
    $this->recipes->adjustWallet($this->seller->id, '120.50', 'opening', walletRef(LedgerReferenceType::ADJUSTMENT), LedgerActor::system());
    $this->recipes->chargeCommission(sellerOrder($this->seller, '30.00'), LedgerActor::system());

    $this->actingAs($this->seller, 'sanctum')->getJson('/api/v1/account/wallet')
        ->assertOk()
        ->assertJsonPath('data.available_balance', '120.50')
        ->assertJsonPath('data.withdrawable_balance', '90.50')
        ->assertJsonPath('data.commission_debt', '30.00')
        ->assertJsonPath('data.can_accept_orders', true);
});

it('stops new orders once the debt reaches the ceiling', function (): void {
    $this->recipes->chargeCommission(sellerOrder($this->seller, '50.00'), LedgerActor::system());

    $this->actingAs($this->seller, 'sanctum')->getJson('/api/v1/account/wallet')
        ->assertOk()
        ->assertJsonPath('data.commission_debt', '50.00')
        ->assertJsonPath('data.can_accept_orders', false);
});

it('lists the statement newest first with direction, running balance and a localized description', function (): void {
    $order = sellerOrder($this->seller, '12.00');
    $this->recipes->chargeCommission($order, LedgerActor::system());
    $this->travel(1)->minutes();
    $this->recipes->settleCommissionByBankTransfer($this->seller->id, '5.00', walletRef(LedgerReferenceType::SETTLEMENT), LedgerActor::system());

    $response = $this->actingAs($this->seller, 'sanctum')
        ->getJson('/api/v1/account/wallet/transactions?lang=en')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($response->json('data.0'))->toMatchArray([
        'type' => 'commission_settled',
        'account' => 'commission_receivable',
        'direction' => 'decrease',
        'amount' => '5.00',
        'balance_after' => '7.00',
        'currency' => 'QAR',
        'description' => 'Commission payment',
    ])->and($response->json('data.1'))->toMatchArray([
        'type' => 'commission_charged',
        'direction' => 'increase',
        'amount' => '12.00',
        'balance_after' => '12.00',
        'reference' => ['type' => 'order', 'id' => $order->id],
    ]);

    $this->actingAs($this->seller, 'sanctum')
        ->getJson('/api/v1/account/wallet/transactions?lang=ar')
        ->assertJsonPath('data.0.description', 'تسديد العمولة');
});

it('filters the statement by type and account and pages with a cursor', function (): void {
    config(['qbazaar.wallet.statement_per_page' => 2]);
    $this->recipes->adjustWallet($this->seller->id, '40.00', 'opening', walletRef(LedgerReferenceType::ADJUSTMENT), LedgerActor::system());

    foreach (['3.00', '4.00', '5.00'] as $i => $commission) {
        $this->travel($i + 1)->minutes();
        $this->recipes->chargeCommission(sellerOrder($this->seller, $commission), LedgerActor::system());
    }

    $page = $this->actingAs($this->seller, 'sanctum')->getJson('/api/v1/account/wallet/transactions?type=commission_charged')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.amount', '5.00')
        ->assertJsonPath('meta.has_more', true);

    $this->actingAs($this->seller, 'sanctum')->getJson('/api/v1/account/wallet/transactions?type=commission_charged&cursor=' . $page->json('meta.next_cursor'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.amount', '3.00');

    $this->actingAs($this->seller, 'sanctum')->getJson('/api/v1/account/wallet/transactions?account=wallet')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'adjustment')
        ->assertJsonPath('data.0.direction', 'increase');

    $this->actingAs($this->seller, 'sanctum')->getJson('/api/v1/account/wallet/transactions?account=revenue&type=bogus')
        ->assertStatus(422);
});

it('never shows another user\'s or the platform\'s lines', function (): void {
    $other = User::factory()->create();
    $this->recipes->chargeCommission(sellerOrder($other, '9.00'), LedgerActor::system());
    $this->recipes->purchasePromotionByBankTransfer('20.00', walletRef(LedgerReferenceType::PROMOTION), LedgerActor::system());

    $this->actingAs($this->seller, 'sanctum')->getJson('/api/v1/account/wallet/transactions')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('requires authentication', function (): void {
    $this->getJson('/api/v1/account/wallet')->assertUnauthorized();
    $this->getJson('/api/v1/account/wallet/transactions')->assertUnauthorized();
});

it('runs a constant number of queries however long the statement page is', function (): void {
    foreach (range(1, 15) as $i) {
        $this->recipes->chargeCommission(sellerOrder($this->seller, '1.00'), LedgerActor::system());
    }

    DB::enableQueryLog();
    $this->actingAs($this->seller, 'sanctum')->getJson('/api/v1/account/wallet/transactions')->assertOk()->assertJsonCount(15, 'data');
    $queries = count(DB::getQueryLog());

    expect($queries)->toBeLessThan(10);
});
