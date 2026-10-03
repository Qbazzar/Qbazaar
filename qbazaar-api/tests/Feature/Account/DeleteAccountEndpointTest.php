<?php

declare(strict_types=1);

use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerReference;
use App\Enums\LedgerReferenceType;
use App\Enums\OrderStatus;
use App\Enums\UserStatus;
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

uses(RefreshDatabase::class);

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
