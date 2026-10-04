<?php

declare(strict_types=1);

use App\Enums\LedgerAccountType;
use App\Enums\LedgerTransactionType;
use App\Enums\SettlementStatus;
use App\Exceptions\ErrorCode;
use App\Models\CommissionSettlement;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Notifications\Finance\FinanceReviewRequestedNotification;
use App\Notifications\Finance\WalletActivityNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ManagesMoney;

uses(RefreshDatabase::class, ManagesMoney::class);

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
    Notification::fake();
    $this->seller = User::factory()->create();
});

function settleByTransfer(User $seller, string $amount = '40.00', array $extra = [])
{
    return test()->actingAs($seller, 'sanctum')->post('/api/v1/account/wallet/settlements', [
        'method' => 'bank_transfer',
        'amount' => $amount,
        'bank_reference' => 'TRX-2026-0042',
        'proof' => UploadedFile::fake()->image('receipt.php.jpg', 600, 800),
        ...$extra,
    ], ['Accept' => 'application/json']);
}

it('records a bank-transfer settlement as pending, keeps the receipt private and tells finance staff', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $finance = User::factory()->create();
    $finance->assignRole('super_admin');
    $this->owesCommission($this->seller, '100.00');

    settleByTransfer($this->seller)
        ->assertCreated()
        ->assertJsonPath('data.method', 'bank_transfer')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.amount', '40.00')
        ->assertJsonPath('data.bank_reference', 'TRX-2026-0042');

    $settlement = CommissionSettlement::query()->sole();
    $proof = $settlement->proof();

    expect($proof->disk)->toBe('local')
        ->and($proof->file_name)->not->toContain('receipt')
        ->and($proof->file_name)->toEndWith('.jpg')
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and($this->balanceOf(LedgerAccountType::USER_COMMISSION_RECEIVABLE, $this->seller))->toBe('100.00')
        ->and(LedgerTransaction::query()->where('type', LedgerTransactionType::COMMISSION_SETTLED->value)->exists())->toBeFalse();

    Notification::assertSentTo($this->seller, WalletActivityNotification::class);
    Notification::assertSentTo($finance, FinanceReviewRequestedNotification::class);
});

it('allows only one pending bank transfer per seller', function (): void {
    $this->owesCommission($this->seller, '100.00');
    settleByTransfer($this->seller)->assertCreated();

    settleByTransfer($this->seller, '10.00')
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::SETTLEMENT_PENDING_EXISTS->value);

    expect(CommissionSettlement::query()->count())->toBe(1);
});

it('refuses to settle more than is owed', function (): void {
    $this->owesCommission($this->seller, '30.00');

    settleByTransfer($this->seller, '30.01')
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::WALLET_EXCEEDS_COMMISSION_DEBT->value);

    expect(CommissionSettlement::query()->count())->toBe(0);
});

it('requires a reference and a receipt for a bank transfer', function (): void {
    $this->owesCommission($this->seller, '30.00');

    $this->actingAs($this->seller, 'sanctum')->postJson('/api/v1/account/wallet/settlements', [
        'method' => 'bank_transfer',
        'amount' => '10.00',
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('rejects amounts with more than two decimals or below one dirham', function (string $amount): void {
    $this->owesCommission($this->seller, '30.00');

    $this->actingAs($this->seller, 'sanctum')->postJson('/api/v1/account/wallet/settlements', [
        'method' => 'wallet',
        'amount' => $amount,
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
})->with(['three decimals' => '1.005', 'zero' => '0', 'negative' => '-5.00', 'text' => 'ten']);

it('nets the debt from the wallet at once', function (): void {
    $this->owesCommission($this->seller, '50.00');
    $this->fundWallet($this->seller, '80.00');

    $this->actingAs($this->seller, 'sanctum')->postJson('/api/v1/account/wallet/settlements', [
        'method' => 'wallet',
        'amount' => '50.00',
    ])->assertCreated()
        ->assertJsonPath('data.method', 'wallet')
        ->assertJsonPath('data.status', 'approved');

    $settlement = CommissionSettlement::query()->sole();

    expect($settlement->status)->toBe(SettlementStatus::APPROVED)
        ->and($this->balanceOf(LedgerAccountType::USER_COMMISSION_RECEIVABLE, $this->seller))->toBe('0.00')
        ->and($this->balanceOf(LedgerAccountType::USER_WALLET, $this->seller))->toBe('30.00')
        ->and(LedgerTransaction::query()->where('idempotency_key', "settlement:{$settlement->id}:commission_settled")->exists())->toBeTrue();
});

it('refuses netting beyond the wallet balance and leaves no settlement behind', function (): void {
    $this->owesCommission($this->seller, '50.00');
    $this->fundWallet($this->seller, '20.00');

    $this->actingAs($this->seller, 'sanctum')->postJson('/api/v1/account/wallet/settlements', [
        'method' => 'wallet',
        'amount' => '50.00',
    ])->assertStatus(422)->assertJsonPath('error.code', ErrorCode::WALLET_INSUFFICIENT_BALANCE->value);

    expect(CommissionSettlement::query()->count())->toBe(0)
        ->and($this->balanceOf(LedgerAccountType::USER_COMMISSION_RECEIVABLE, $this->seller))->toBe('50.00');
});

it('lists only the caller\'s settlements, newest first', function (): void {
    $this->owesCommission($this->seller, '50.00');
    $this->fundWallet($this->seller, '50.00');
    $this->actingAs($this->seller, 'sanctum')->postJson('/api/v1/account/wallet/settlements', ['method' => 'wallet', 'amount' => '10.00'])->assertCreated();
    $this->travel(1)->seconds();
    settleByTransfer($this->seller, '20.00')->assertCreated();
    $stranger = User::factory()->create();
    $this->owesCommission($stranger, '5.00');
    settleByTransfer($stranger, '5.00')->assertCreated();

    $this->actingAs($this->seller, 'sanctum')->getJson('/api/v1/account/wallet/settlements')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.amount', '20.00')
        ->assertJsonPath('data.1.amount', '10.00');
});
