<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerReference;
use App\Enums\FinanceNotice;
use App\Enums\FinanceReview;
use App\Enums\LedgerReferenceType;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\Finance\WalletActivityNotification;
use App\Services\Finance\BankAccountService;
use App\Services\Finance\FinanceStaff;
use App\Services\Ledger\LedgerRecipes;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * A seller asks to be paid part or all of their wallet balance. The amount
 * leaves the wallet in the same transaction, so it cannot be spent or
 * withdrawn twice while finance staff review it; the ledger refuses more
 * than the wallet holds (WALLET_001).
 */
class RequestWithdrawalAction
{
    public function __construct(
        private readonly LedgerRecipes $recipes,
        private readonly BankAccountService $bankAccounts,
        private readonly FinanceStaff $staff,
    ) {}

    public function __invoke(User $seller, string $amount, ?string $bankAccountId): Withdrawal
    {
        $amount = Money::of($amount);
        $account = $this->bankAccounts->payoutAccount($seller, $bankAccountId);

        $withdrawal = DB::transaction(function () use ($seller, $amount, $account): Withdrawal {
            $withdrawal = new Withdrawal;
            $withdrawal->forceFill([
                'user_id' => $seller->id,
                'bank_account_id' => $account->id,
                'status' => WithdrawalStatus::PENDING,
                'amount' => $amount,
                'holder_name' => $account->holder_name,
                'iban' => $account->iban,
                'iban_last4' => $account->iban_last4,
            ])->save();

            $this->recipes->requestWithdrawal(
                $seller->id,
                $amount,
                new LedgerReference(LedgerReferenceType::WITHDRAWAL, $withdrawal->id),
                LedgerActor::user($seller),
            );

            return $withdrawal;
        });

        $seller->notify(new WalletActivityNotification(FinanceNotice::WITHDRAWAL_REQUESTED, $withdrawal->id, ['amount' => $amount]));
        $this->staff->requestReview(FinanceReview::WITHDRAWAL, $amount);

        return $withdrawal;
    }
}
