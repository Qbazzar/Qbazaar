<?php

declare(strict_types=1);

use App\Actions\Finance\SubmitSettlementAction;
use App\Actions\Messaging\StartConversationAction;
use App\Actions\PurchaseRequests\AcceptPurchaseRequestAction;
use App\Actions\PurchaseRequests\CreatePurchaseRequestAction;
use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerLine;
use App\Enums\LedgerAccountType as Account;
use App\Enums\LedgerTransactionType as Flow;
use App\Enums\OrderStatus;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Orders\OrderTransitionService;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesAds;
use Tests\Concerns\ManagesMoney;
use Tests\Concerns\PlacesOrders;

/*
 * Real row locks need a real InnoDB, so this group runs against MySQL only
 * (CI job "API · MySQL locking"; locally see DEV-SETUP.md). The setup is
 * committed; the operation under test then runs in a transaction that stays
 * open, playing the first request, while a second connection plays the
 * concurrent one and must be made to wait, which a one-second lock wait
 * timeout turns into error 1205.
 */
uses(DatabaseTruncation::class, CreatesAds::class, ManagesMoney::class, PlacesOrders::class)->group('mysql');

const MYSQL_LOCK_WAIT_TIMEOUT = 1205;

beforeEach(function (): void {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Row locking is only observable on MySQL.');
    }

    config(['database.connections.concurrent' => config('database.connections.mysql')]);
    $this->concurrent = DB::connection('concurrent');
    $this->concurrent->statement('SET SESSION innodb_lock_wait_timeout = 1');
});

afterEach(function (): void {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }

    DB::purge('concurrent');
});

function expectToWaitForTheLock(callable $concurrentRequest): void
{
    try {
        $concurrentRequest();
    } catch (QueryException $exception) {
        expect($exception->errorInfo[1] ?? null)->toBe(MYSQL_LOCK_WAIT_TIMEOUT);

        return;
    }

    test()->fail('The concurrent request did not wait for the lock.');
}

function postLockedCommission(string $sellerId, string $key): void
{
    app(LedgerService::class)->post(Flow::COMMISSION_CHARGED, [
        LedgerLine::credit(Account::PLATFORM_REVENUE, '10.00'),
        LedgerLine::debit(Account::USER_COMMISSION_RECEIVABLE, '10.00', $sellerId),
    ], $key, null, LedgerActor::system());
}

it('keeps the accounts of a posting locked until it commits', function (): void {
    $sellerId = (string) Str::ulid();
    DB::beginTransaction();
    postLockedCommission($sellerId, 'race:posting');

    foreach (['platform:revenue', "user:{$sellerId}:commission_receivable"] as $code) {
        expectToWaitForTheLock(fn () => $this->concurrent->selectOne('SELECT id FROM ledger_accounts WHERE code = ? FOR UPDATE', [$code]));
    }
});

it('makes a concurrent posting with the same idempotency key wait instead of posting twice', function (): void {
    DB::beginTransaction();
    postLockedCommission((string) Str::ulid(), 'race:duplicate');

    expectToWaitForTheLock(fn () => $this->concurrent->table('ledger_transactions')->insert([
        'id' => (string) Str::ulid(),
        'type' => Flow::COMMISSION_CHARGED->value,
        'idempotency_key' => 'race:duplicate',
        'created_by_type' => 'system',
        'posted_at' => now(),
    ]));
});

it('locks the accounts of a posting in id order, whatever the order of its lines', function (): void {
    DB::enableQueryLog();
    postLockedCommission((string) Str::ulid(), 'race:order');

    $lock = collect(DB::getQueryLog())->pluck('query')
        ->first(fn (string $sql): bool => str_contains($sql, 'from `ledger_accounts`') && str_contains($sql, 'for update'));

    expect($lock)->toContain('order by `id` asc for update');
});

it('holds the ad and the order for the whole of a transition', function (): void {
    $this->seedReferenceData();
    $order = $this->orderAwaitingHandover(User::factory()->create(), User::factory()->create());

    DB::beginTransaction();
    app(OrderTransitionService::class)->dispute($order);
    expect($order->status)->toBe(OrderStatus::DISPUTED);

    // FOR SHARE, because the order's foreign key alone already holds a shared lock on the ad.
    expectToWaitForTheLock(fn () => $this->concurrent->selectOne('SELECT id FROM ads WHERE id = ? FOR SHARE', [$order->ad_id]));
    expectToWaitForTheLock(fn () => $this->concurrent->selectOne('SELECT id FROM orders WHERE id = ? FOR UPDATE', [$order->id]));
});

it('makes a second open order on the same ad wait on the unique index', function (): void {
    $this->seedReferenceData();
    DB::beginTransaction();
    $order = $this->placeOrder(User::factory()->create(), User::factory()->create());

    expectToWaitForTheLock(fn () => $this->concurrent->table('orders')->insert([
        'id' => (string) Str::ulid(),
        'active_ad_id' => $order->ad_id,
        'source' => 'purchase_request',
        'source_id' => (string) Str::ulid(),
        'status' => OrderStatus::CREATED->value,
        'ad_title' => 'Racing order',
        'unit_price' => '1.00',
        'total' => '1.00',
        'commission_rate' => '5.00',
        'commission_amount' => '0.05',
        'payment_method' => 'cash',
        'created_at' => now(),
        'updated_at' => now(),
    ]));
});

it('holds the ad and the request while the seller accepts a purchase request', function (): void {
    $this->seedReferenceData();
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $ad = $this->makeAd($seller, ['status' => 'active', 'price' => '250.00']);
    $request = app(CreatePurchaseRequestAction::class)($buyer, $ad, 1, null);

    DB::beginTransaction();
    app(AcceptPurchaseRequestAction::class)($seller, $request);

    expectToWaitForTheLock(fn () => $this->concurrent->selectOne('SELECT id FROM ads WHERE id = ? FOR UPDATE', [$ad->id]));
    expectToWaitForTheLock(fn () => $this->concurrent->selectOne('SELECT id FROM purchase_requests WHERE id = ? FOR UPDATE', [$request->id]));
});

it('makes a second open purchase request by the same buyer wait on the unique index', function (): void {
    $this->seedReferenceData();
    $buyer = User::factory()->create();
    $ad = $this->makeAd(User::factory()->create(), ['status' => 'active', 'price' => '250.00']);
    // Committed up front, so the only lock left to wait on is the unique index.
    $conversation = app(StartConversationAction::class)->execute($buyer, $ad)['conversation'];

    DB::beginTransaction();
    app(CreatePurchaseRequestAction::class)($buyer, $ad, 1, null);

    expectToWaitForTheLock(fn () => $this->concurrent->table('purchase_requests')->insert([
        'id' => (string) Str::ulid(),
        'conversation_id' => $conversation->id,
        'ad_id' => $ad->id,
        'buyer_id' => $buyer->id,
        'seller_id' => $ad->user_id,
        'unit_price' => '250.00',
        'status' => 'pending',
        'is_open' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]));
});

it('holds the seller\'s ledger accounts while a bank-transfer settlement is submitted', function (): void {
    Storage::fake('local');
    $seller = User::factory()->create();
    $this->owesCommission($seller, '100.00');

    DB::beginTransaction();
    app(SubmitSettlementAction::class)->byBankTransfer($seller, '40.00', 'TRX-1', UploadedFile::fake()->image('receipt.jpg'));

    expectToWaitForTheLock(fn () => $this->concurrent->selectOne('SELECT id FROM ledger_accounts WHERE code = ? FOR UPDATE', ["user:{$seller->id}:commission_receivable"]));
});
