<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerReference;
use App\Enums\LedgerAccountType;
use App\Enums\LedgerReferenceType;
use App\Enums\SettlementMethod;
use App\Enums\SettlementStatus;
use App\Enums\WithdrawalStatus;
use App\Models\CommissionSettlement;
use App\Models\Order;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Ledger\LedgerAccounts;
use App\Services\Ledger\LedgerRecipes;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Puts a user's books in a known state through the real ledger recipes.
 */
trait ManagesMoney
{
    protected const string QATAR_IBAN = 'QA58 DOHB 0000 1234 5678 90AB CDEF G';

    protected const string SAUDI_IBAN = 'SA0380000000608010167519';

    protected function fundWallet(User $user, string $amount): void
    {
        app(LedgerRecipes::class)->adjustWallet(
            $user->id,
            $amount,
            'test funding',
            new LedgerReference(LedgerReferenceType::ADJUSTMENT, (string) Str::ulid()),
            LedgerActor::system(),
        );
    }

    protected function owesCommission(User $seller, string $amount): void
    {
        $order = Order::factory()->create(['ad_id' => null, 'seller_id' => $seller->id, 'commission_amount' => $amount]);

        app(LedgerRecipes::class)->chargeCommission($order, LedgerActor::system());
    }

    protected function withdrawalOf(User $user, WithdrawalStatus $status): Withdrawal
    {
        $withdrawal = new Withdrawal;
        $withdrawal->forceFill([
            'user_id' => $user->id,
            'status' => $status,
            'amount' => '10.00',
            'holder_name' => 'Ahmed Al-Ali',
            'iban' => 'QA58DOHB00001234567890ABCDEFG',
            'iban_last4' => 'DEFG',
        ])->save();

        return $withdrawal;
    }

    protected function settlementOf(User $user, SettlementStatus $status): CommissionSettlement
    {
        $settlement = new CommissionSettlement;
        $settlement->forceFill([
            'user_id' => $user->id,
            'method' => SettlementMethod::BANK_TRANSFER,
            'status' => $status,
            'amount' => '10.00',
        ])->save();

        return $settlement;
    }

    protected function balanceOf(LedgerAccountType $account, ?User $owner = null): string
    {
        return app(LedgerAccounts::class)->balanceOf($account, $owner?->id);
    }

    public function addBankAccount(User $user, string $iban = self::QATAR_IBAN, array $extra = []): TestResponse
    {
        return $this->actingAs($user, 'sanctum')->postJson('/api/v1/account/bank-accounts', [
            'holder_name' => 'Ahmed Al-Ali',
            'iban' => $iban,
            ...$extra,
        ]);
    }
}
