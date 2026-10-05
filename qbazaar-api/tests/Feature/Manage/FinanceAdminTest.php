<?php

declare(strict_types=1);

use App\Enums\LedgerAccountType;
use App\Enums\LedgerTransactionType;
use App\Enums\SettlementStatus;
use App\Enums\WithdrawalStatus;
use App\Models\CommissionSettlement;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\Finance\WalletActivityNotification;
use App\Services\Ledger\WalletService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\ManagesMoney;

uses(RefreshDatabase::class, ManagesMoney::class);

beforeEach(function (): void {
    $this->withoutVite();
    Storage::fake('local');
    Notification::fake();
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
    $this->seller = User::factory()->create();
});

function pendingTransfer(User $seller, string $amount): CommissionSettlement
{
    test()->actingAs($seller, 'sanctum')->post('/api/v1/account/wallet/settlements', [
        'method' => 'bank_transfer',
        'amount' => $amount,
        'bank_reference' => 'TRX-1',
        'proof' => UploadedFile::fake()->image('receipt.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated();

    return CommissionSettlement::query()->where('user_id', $seller->id)->latest()->firstOrFail();
}

function pendingWithdrawal(User $seller, string $amount): Withdrawal
{
    test()->addBankAccount($seller)->assertCreated();
    test()->actingAs($seller, 'sanctum')->postJson('/api/v1/account/wallet/withdrawals', ['amount' => $amount])->assertCreated();

    return Withdrawal::query()->where('user_id', $seller->id)->latest()->firstOrFail();
}

it('approves a bank transfer: bank debited, the seller\'s debt credited, once', function (): void {
    $this->owesCommission($this->seller, '100.00');
    $settlement = pendingTransfer($this->seller, '60.00');

    actingAs($this->admin)->post(route('admin.finance.settlements.approve', $settlement))->assertRedirect()->assertSessionHas('status');
    actingAs($this->admin)->post(route('admin.finance.settlements.approve', $settlement))->assertRedirect()->assertSessionHas('error');

    $settlement->refresh();
    expect($settlement->status)->toBe(SettlementStatus::APPROVED)
        ->and($settlement->reviewed_by)->toBe($this->admin->id)
        ->and($settlement->pending_user_id)->toBeNull()
        ->and($this->balanceOf(LedgerAccountType::USER_COMMISSION_RECEIVABLE, $this->seller))->toBe('40.00')
        ->and($this->balanceOf(LedgerAccountType::PLATFORM_BANK))->toBe('60.00')
        ->and(LedgerTransaction::query()->where('type', LedgerTransactionType::COMMISSION_SETTLED->value)->count())->toBe(1)
        ->and(Activity::query()->where('log_name', 'finance')->where('event', 'settlement_approved')->count())->toBe(1);

    Notification::assertSentTo($this->seller, WalletActivityNotification::class, fn (WalletActivityNotification $n): bool => $n->notice->value === 'settlement_approved');
});

it('lets a seller over the debt ceiling take orders again once the settlement is approved', function (): void {
    config(['qbazaar.commission.debt_ceiling' => '50.00']);
    $this->owesCommission($this->seller, '80.00');
    $wallets = app(WalletService::class);
    expect($wallets->canAcceptOrders($this->seller->id))->toBeFalse();

    $settlement = pendingTransfer($this->seller, '40.00');
    expect($wallets->canAcceptOrders($this->seller->id))->toBeFalse();

    actingAs($this->admin)->post(route('admin.finance.settlements.approve', $settlement))->assertRedirect();

    expect($wallets->canAcceptOrders($this->seller->id))->toBeTrue();
});

it('rejects a settlement with a reason, posts nothing and frees the seller to submit again', function (): void {
    $this->owesCommission($this->seller, '100.00');
    $settlement = pendingTransfer($this->seller, '60.00');

    actingAs($this->admin)->post(route('admin.finance.settlements.reject', $settlement), ['reason' => 'No such transfer on the statement'])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($settlement->refresh()->status)->toBe(SettlementStatus::REJECTED)
        ->and($settlement->rejection_reason)->toBe('No such transfer on the statement')
        ->and($this->balanceOf(LedgerAccountType::USER_COMMISSION_RECEIVABLE, $this->seller))->toBe('100.00');

    pendingTransfer($this->seller, '60.00');
});

it('streams the receipt to finance staff only', function (): void {
    $this->owesCommission($this->seller, '100.00');
    $settlement = pendingTransfer($this->seller, '60.00');

    actingAs($this->admin)->get(route('admin.finance.settlements.proof', $settlement))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');
    actingAs($moderator)->get(route('admin.finance.settlements.proof', $settlement))->assertForbidden();
});

it('marks a withdrawal paid out of the bank with the transfer reference', function (): void {
    $this->fundWallet($this->seller, '200.00');
    $withdrawal = pendingWithdrawal($this->seller, '150.00');

    actingAs($this->admin)->post(route('admin.finance.withdrawals.pay', $withdrawal), ['transfer_reference' => 'OUT-77'])->assertRedirect()->assertSessionHas('status');

    expect($withdrawal->refresh()->status)->toBe(WithdrawalStatus::PAID)
        ->and($withdrawal->transfer_reference)->toBe('OUT-77')
        ->and($this->balanceOf(LedgerAccountType::PLATFORM_PAYOUTS_PAYABLE))->toBe('0.00')
        ->and($this->balanceOf(LedgerAccountType::PLATFORM_BANK))->toBe('-150.00')
        ->and($this->balanceOf(LedgerAccountType::USER_WALLET, $this->seller))->toBe('50.00');

    Notification::assertSentTo($this->seller, WalletActivityNotification::class, fn (WalletActivityNotification $n): bool => $n->notice->value === 'withdrawal_paid');
});

it('rejects a withdrawal back into the wallet, and a paid one cannot be rejected', function (): void {
    $this->fundWallet($this->seller, '200.00');
    $withdrawal = pendingWithdrawal($this->seller, '150.00');

    actingAs($this->admin)->post(route('admin.finance.withdrawals.reject', $withdrawal), ['reason' => 'Holder name does not match'])->assertRedirect()->assertSessionHas('status');
    actingAs($this->admin)->post(route('admin.finance.withdrawals.pay', $withdrawal), ['transfer_reference' => 'OUT-78'])->assertRedirect()->assertSessionHas('error');

    expect($withdrawal->refresh()->status)->toBe(WithdrawalStatus::REJECTED)
        ->and($this->balanceOf(LedgerAccountType::USER_WALLET, $this->seller))->toBe('200.00')
        ->and($this->balanceOf(LedgerAccountType::PLATFORM_PAYOUTS_PAYABLE))->toBe('0.00')
        ->and($this->balanceOf(LedgerAccountType::PLATFORM_BANK))->toBe('0.00')
        ->and(LedgerTransaction::query()->where('idempotency_key', "withdrawal:{$withdrawal->id}:outcome")->count())->toBe(1);
});

it('lets the ledger outcome key stop a second outcome even past the status check', function (): void {
    $this->fundWallet($this->seller, '200.00');
    $withdrawal = pendingWithdrawal($this->seller, '150.00');

    actingAs($this->admin)->post(route('admin.finance.withdrawals.pay', $withdrawal), ['transfer_reference' => 'OUT-79'])->assertRedirect();
    Withdrawal::query()->whereKey($withdrawal->id)->update(['status' => WithdrawalStatus::PENDING->value]);

    $this->withoutExceptionHandling();
    expect(fn () => actingAs($this->admin)->post(route('admin.finance.withdrawals.reject', $withdrawal), ['reason' => 'Changed my mind']))
        ->toThrow(LogicException::class, 'already belongs to a withdrawal_paid transaction');

    expect($this->balanceOf(LedgerAccountType::USER_WALLET, $this->seller))->toBe('50.00')
        ->and($this->balanceOf(LedgerAccountType::PLATFORM_BANK))->toBe('-150.00');
});

it('shows the queues and the statement, the full IBAN only to staff who pay', function (): void {
    $this->owesCommission($this->seller, '100.00');
    pendingTransfer($this->seller, '60.00');
    $this->fundWallet($this->seller, '300.00');
    pendingWithdrawal($this->seller, '150.00');

    actingAs($this->admin)->get(route('admin.finance.settlements.index'))->assertOk()->assertSee('60.00')->assertSee('TRX-1');
    actingAs($this->admin)->get(route('admin.finance.withdrawals.index'))->assertOk()->assertSee('QA58DOHB00001234567890ABCDEFG');
    actingAs($this->admin)->get(route('admin.finance.disputes.index'))->assertOk();
    actingAs($this->admin)->get(route('admin.finance.statements.show', $this->seller))->assertOk()->assertSee('110.00')->assertSee('100.00');

    $viewer = User::factory()->create();
    $viewer->assignRole('support');
    $viewer->givePermissionTo('finance.view');
    actingAs($viewer)->get(route('admin.finance.withdrawals.index'))
        ->assertOk()
        ->assertDontSee('QA58DOHB00001234567890ABCDEFG')
        ->assertSee('QA** **** DEFG')
        ->assertDontSee(route('admin.finance.withdrawals.pay', Withdrawal::query()->sole()), false);
});

it('refuses finance actions to staff without finance.manage', function (string $role): void {
    $this->fundWallet($this->seller, '200.00');
    $withdrawal = pendingWithdrawal($this->seller, '150.00');
    $staff = User::factory()->create();
    $staff->assignRole($role);
    $staff->givePermissionTo('finance.view');

    actingAs($staff)->post(route('admin.finance.withdrawals.pay', $withdrawal), ['transfer_reference' => 'OUT-80'])->assertForbidden();

    expect($withdrawal->refresh()->status)->toBe(WithdrawalStatus::PENDING);
})->with(['moderator', 'support']);
