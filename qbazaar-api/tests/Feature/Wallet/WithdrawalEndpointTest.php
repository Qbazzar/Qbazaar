<?php

declare(strict_types=1);

use App\Enums\LedgerAccountType;
use App\Enums\WithdrawalStatus;
use App\Exceptions\ErrorCode;
use App\Models\BankAccount;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\Finance\WalletActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\ManagesMoney;

uses(RefreshDatabase::class, ManagesMoney::class);

beforeEach(function (): void {
    Notification::fake();
    $this->seller = User::factory()->create();
});

function requestWithdrawal(User $seller, string $amount, array $extra = [])
{
    return test()->actingAs($seller, 'sanctum')->postJson('/api/v1/account/wallet/withdrawals', ['amount' => $amount, ...$extra]);
}

it('moves the amount out of the wallet into payouts payable at once', function (): void {
    $this->fundWallet($this->seller, '300.00');
    $this->addBankAccount($this->seller)->assertCreated();

    requestWithdrawal($this->seller, '250.00')
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.amount', '250.00')
        ->assertJsonPath('data.holder_name', 'Ahmed Al-Ali')
        ->assertJsonPath('data.iban_masked', 'QA** **** DEFG')
        ->assertJsonMissingPath('data.iban');

    expect($this->balanceOf(LedgerAccountType::USER_WALLET, $this->seller))->toBe('50.00')
        ->and($this->balanceOf(LedgerAccountType::PLATFORM_PAYOUTS_PAYABLE))->toBe('250.00');

    Notification::assertSentTo($this->seller, WalletActivityNotification::class);
});

it('refuses more than the available balance and leaves no withdrawal behind', function (): void {
    $this->fundWallet($this->seller, '100.00');
    $this->addBankAccount($this->seller)->assertCreated();

    requestWithdrawal($this->seller, '100.01')
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::WALLET_INSUFFICIENT_BALANCE->value);

    expect(Withdrawal::query()->count())->toBe(0)
        ->and($this->balanceOf(LedgerAccountType::USER_WALLET, $this->seller))->toBe('100.00');
});

it('requires a bank account', function (): void {
    $this->fundWallet($this->seller, '100.00');

    requestWithdrawal($this->seller, '10.00')
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::WITHDRAWAL_BANK_ACCOUNT_REQUIRED->value);
});

it('pays only into the caller\'s own bank account', function (): void {
    $this->fundWallet($this->seller, '100.00');
    $this->addBankAccount($this->seller)->assertCreated();
    $stranger = User::factory()->create();
    $strangersAccount = $this->addBankAccount($stranger, 'SA0380000000608010167519')->json('data.id');

    requestWithdrawal($this->seller, '10.00', ['bank_account_id' => $strangersAccount])
        ->assertNotFound()
        ->assertJsonPath('error.code', ErrorCode::BANK_ACCOUNT_NOT_FOUND->value);
});

it('keeps the payout destination when the bank account is deleted afterwards', function (): void {
    $this->fundWallet($this->seller, '100.00');
    $accountId = $this->addBankAccount($this->seller)->json('data.id');
    requestWithdrawal($this->seller, '10.00')->assertCreated();

    $this->actingAs($this->seller, 'sanctum')->deleteJson('/api/v1/account/bank-accounts/' . $accountId)->assertNoContent();

    $withdrawal = Withdrawal::query()->sole();
    expect(BankAccount::query()->count())->toBe(0)
        ->and($withdrawal->bank_account_id)->toBeNull()
        ->and($withdrawal->iban)->toBe('QA58DOHB00001234567890ABCDEFG')
        ->and($withdrawal->status)->toBe(WithdrawalStatus::PENDING);
});

it('replays a retried request with the same idempotency key instead of withdrawing twice', function (): void {
    $this->fundWallet($this->seller, '100.00');
    $this->addBankAccount($this->seller)->assertCreated();
    $headers = ['X-Idempotency-Key' => '01HZY7Q4C9V3N8B2K6M5T1R0WX'];

    $this->actingAs($this->seller, 'sanctum')->postJson('/api/v1/account/wallet/withdrawals', ['amount' => '40.00'], $headers)->assertCreated();
    $this->actingAs($this->seller, 'sanctum')->postJson('/api/v1/account/wallet/withdrawals', ['amount' => '40.00'], $headers)
        ->assertCreated()
        ->assertHeader('X-Idempotent-Replay', 'true');

    expect(Withdrawal::query()->count())->toBe(1)
        ->and($this->balanceOf(LedgerAccountType::USER_WALLET, $this->seller))->toBe('60.00');
});

it('lists only the caller\'s withdrawals', function (): void {
    $this->fundWallet($this->seller, '100.00');
    $this->addBankAccount($this->seller)->assertCreated();
    requestWithdrawal($this->seller, '10.00')->assertCreated();

    $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/account/wallet/withdrawals')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($this->seller, 'sanctum')->getJson('/api/v1/account/wallet/withdrawals')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.iban_masked', 'QA** **** DEFG');
});
