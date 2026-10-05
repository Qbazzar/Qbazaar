<?php

declare(strict_types=1);

use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerReference;
use App\Enums\LedgerReferenceType;
use App\Enums\OrderStatus;
use App\Enums\SettlementStatus;
use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Jobs\DeleteAccountJob;
use App\Models\Order;
use App\Models\User;
use App\Services\Ledger\LedgerRecipes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;

use Tests\Concerns\ManagesMoney;

uses(RefreshDatabase::class, ManagesMoney::class);

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'password' => Hash::make('Str0ng!Pass1'),
    ]);
    Sanctum::actingAs($this->user, ['*']);
    Bus::fake();
});

it('schedules deletion, flips status to PENDING_DELETION, and queues DeleteAccountJob', function (): void {
    deleteJson('/api/v1/account/delete-request', [
        'password' => 'Str0ng!Pass1',
        'reason' => 'Done with classifieds',
    ])
        ->assertStatus(202)
        ->assertJson(
            fn ($json) => $json
                ->where('success', true)
                ->where('data.status', 'pending_deletion')
                ->has('data.deletion_scheduled_at')
                ->etc(),
        );

    $fresh = $this->user->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->status)->toBe(UserStatus::PENDING_DELETION);
    expect($fresh->deletion_requested_at)->not->toBeNull();

    Bus::assertDispatched(DeleteAccountJob::class, function (DeleteAccountJob $job): bool {
        return $job->userId === $this->user->id;
    });
});

it('rejects when the password is wrong with USER_005', function (): void {
    deleteJson('/api/v1/account/delete-request', [
        'password' => 'WrongPass!1',
    ])
        ->assertStatus(422)
        ->assertJson(
            fn ($json) => $json
                ->where('error.code', 'USER_005')
                ->etc(),
        );

    Bus::assertNotDispatched(DeleteAccountJob::class);
    expect($this->user->fresh()->status)->toBe(UserStatus::ACTIVE);
});

it('rejects unauthenticated requests', function (): void {
    $this->refreshApplication();

    deleteJson('/api/v1/account/delete-request', [
        'password' => 'Str0ng!Pass1',
    ])->assertStatus(401);
});

function chargeDeletionTestCommission(User $seller, string $amount): void
{
    $order = Order::factory()->status(OrderStatus::COMPLETED)->create([
        'ad_id' => null,
        'seller_id' => $seller->id,
        'commission_amount' => $amount,
    ]);

    app(LedgerRecipes::class)->chargeCommission($order, LedgerActor::system());
}

function settleDeletionTestCommission(User $seller, string $amount): void
{
    app(LedgerRecipes::class)->settleCommissionByBankTransfer(
        $seller->id,
        $amount,
        new LedgerReference(LedgerReferenceType::SETTLEMENT, (string) Str::ulid()),
        LedgerActor::system(),
    );
}

it('refuses with ACCOUNT_003 and the amount while the user owes commission', function (): void {
    chargeDeletionTestCommission($this->user, '12.50');

    deleteJson('/api/v1/account/delete-request', ['password' => 'Str0ng!Pass1'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ACCOUNT_003')
        ->assertJsonPath('error.details.amount', '12.50')
        ->assertJsonPath('error.details.currency', 'QAR')
        ->assertJsonPath('error.message', __('errors.account.debt.outstanding', ['amount' => '12.50']));

    Bus::assertNotDispatched(DeleteAccountJob::class);
    expect($this->user->fresh()->status)->toBe(UserStatus::ACTIVE)
        ->and($this->user->fresh()->deletion_requested_at)->toBeNull();
});

it('allows the deletion once the commission is settled', function (): void {
    chargeDeletionTestCommission($this->user, '12.50');
    settleDeletionTestCommission($this->user, '12.50');

    deleteJson('/api/v1/account/delete-request', ['password' => 'Str0ng!Pass1'])->assertStatus(202);

    Bus::assertDispatched(DeleteAccountJob::class);
    expect($this->user->fresh()->status)->toBe(UserStatus::PENDING_DELETION);
});

it('checks the password before revealing any debt', function (): void {
    chargeDeletionTestCommission($this->user, '12.50');

    deleteJson('/api/v1/account/delete-request', ['password' => 'WrongPass!1'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'USER_005');
});

it('refuses with ACCOUNT_004 while the user has an order in progress', function (string $party, OrderStatus $status): void {
    $order = Order::factory()->status($status)->create(['ad_id' => null, $party => $this->user->id]);

    deleteJson('/api/v1/account/delete-request', ['password' => 'Str0ng!Pass1'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ACCOUNT_004')
        ->assertJsonPath('error.details.order_id', $order->id);

    Bus::assertNotDispatched(DeleteAccountJob::class);
    expect($this->user->fresh()->status)->toBe(UserStatus::ACTIVE);
})->with([
    'as buyer, created' => ['buyer_id', OrderStatus::CREATED],
    'as seller, awaiting handover' => ['seller_id', OrderStatus::AWAITING_HANDOVER],
    'as buyer, disputed' => ['buyer_id', OrderStatus::DISPUTED],
]);

it('allows the deletion when every order is completed or cancelled', function (): void {
    Order::factory()->status(OrderStatus::COMPLETED)->create(['ad_id' => null, 'buyer_id' => $this->user->id]);
    Order::factory()->status(OrderStatus::CANCELLED)->create(['ad_id' => null, 'seller_id' => $this->user->id]);

    deleteJson('/api/v1/account/delete-request', ['password' => 'Str0ng!Pass1'])->assertStatus(202);
});

function adjustDeletionTestWallet(User $user, string $signedAmount): void
{
    app(LedgerRecipes::class)->adjustWallet(
        $user->id,
        $signedAmount,
        'deletion test',
        new LedgerReference(LedgerReferenceType::ADJUSTMENT, (string) Str::ulid()),
        LedgerActor::system(),
    );
}

it('refuses with ACCOUNT_005 and the balance while the wallet is not empty', function (): void {
    adjustDeletionTestWallet($this->user, '40.00');

    deleteJson('/api/v1/account/delete-request', ['password' => 'Str0ng!Pass1'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ACCOUNT_005')
        ->assertJsonPath('error.details.amount', '40.00')
        ->assertJsonPath('error.details.currency', 'QAR');

    Bus::assertNotDispatched(DeleteAccountJob::class);
    expect($this->user->fresh()->status)->toBe(UserStatus::ACTIVE);
});

it('allows the deletion once the wallet has been emptied', function (): void {
    adjustDeletionTestWallet($this->user, '40.00');
    adjustDeletionTestWallet($this->user, '-40.00');

    deleteJson('/api/v1/account/delete-request', ['password' => 'Str0ng!Pass1'])->assertStatus(202);
});

it('refuses with ACCOUNT_006 while a withdrawal waits for review', function (): void {
    $withdrawal = $this->withdrawalOf($this->user, WithdrawalStatus::PENDING);

    deleteJson('/api/v1/account/delete-request', ['password' => 'Str0ng!Pass1'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ACCOUNT_006')
        ->assertJsonPath('error.details.withdrawal_id', $withdrawal->id);

    Bus::assertNotDispatched(DeleteAccountJob::class);
    expect($this->user->fresh()->status)->toBe(UserStatus::ACTIVE);
});

it('refuses with ACCOUNT_006 while a settlement waits for review', function (): void {
    $settlement = $this->settlementOf($this->user, SettlementStatus::PENDING);

    deleteJson('/api/v1/account/delete-request', ['password' => 'Str0ng!Pass1'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ACCOUNT_006')
        ->assertJsonPath('error.details.settlement_id', $settlement->id);
});

it('allows the deletion once withdrawals and settlements are reviewed', function (): void {
    $this->withdrawalOf($this->user, WithdrawalStatus::PAID);
    $this->withdrawalOf($this->user, WithdrawalStatus::REJECTED);
    $this->settlementOf($this->user, SettlementStatus::APPROVED);
    $this->settlementOf($this->user, SettlementStatus::REJECTED);

    deleteJson('/api/v1/account/delete-request', ['password' => 'Str0ng!Pass1'])->assertStatus(202);
});

it('ignores the pending payouts of other users', function (): void {
    $this->withdrawalOf(User::factory()->create(), WithdrawalStatus::PENDING);
    $this->settlementOf(User::factory()->create(), SettlementStatus::PENDING);

    deleteJson('/api/v1/account/delete-request', ['password' => 'Str0ng!Pass1'])->assertStatus(202);
});
