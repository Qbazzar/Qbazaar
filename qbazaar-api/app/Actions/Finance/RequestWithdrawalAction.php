<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Ledger\LedgerActor;
use App\Data\Ledger\LedgerReference;
use App\Enums\FinanceNotice;
use App\Enums\FinanceReview;
use App\Enums\LedgerAccountType;
use App\Enums\WithdrawalStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\CommissionSettlement;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\Finance\WalletActivityNotification;
use App\Services\Finance\BankAccountService;
use App\Services\Finance\FinanceStaff;
use App\Services\Ledger\LedgerAccounts;
use App\Services\Ledger\LedgerRecipes;
use App\Services\Ledger\WalletService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * A seller asks to be paid part or all of their wallet balance. A seller
 * who owes commission cannot withdraw it away: at most the balance minus
 * the debt can be requested (WALLET_003), and the debt is first netted from
 * the wallet in the same transaction. The amount then leaves the wallet,
 * so it cannot be spent or withdrawn twice while finance staff review it.
 *
 * The seller's accounts are locked first, so two concurrent requests are
 * checked one after the other against the real balances.
 */
class RequestWithdrawalAction
{
    public function __construct(
        private readonly LedgerRecipes $recipes,
        private readonly LedgerAccounts $accounts,
        private readonly WalletService $wallets,
        private readonly BankAccountService $bankAccounts,
        private readonly FinanceStaff $staff,
    ) {}

    public function __invoke(User $seller, string $amount, ?string $bankAccountId): Withdrawal
    {
        $amount = Money::of($amount);
        $account = $this->bankAccounts->payoutAccount($seller, $bankAccountId);

        $withdrawal = DB::transaction(function () use ($seller, $amount, $account): Withdrawal {
            $debt = $this->assertWithdrawable($seller, $amount);

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

            $reference = LedgerReference::withdrawal($withdrawal->id);
            $actor = LedgerActor::user($seller);

            if (Money::isPositive($debt)) {
                $this->recipes->settleCommissionFromWallet($seller->id, $debt, $reference, $actor);
            }

            $this->recipes->requestWithdrawal($seller->id, $amount, $reference, $actor);

            return $withdrawal;
        });

        $seller->notify(new WalletActivityNotification(FinanceNotice::WITHDRAWAL_REQUESTED, $withdrawal->id, ['amount' => $amount]));
        $this->staff->requestReview(FinanceReview::WITHDRAWAL, $amount);

        return $withdrawal;
    }

    /**
     * Under the account locks: refuses more than the wallet minus the debt.
     * Returns the debt to net first, which leaves out what a transfer
     * awaiting review already covers so that transfer stays approvable.
     */
    private function assertWithdrawable(User $seller, string $amount): string
    {
        $owned = $this->accounts->lockUserAccounts($seller->id, LedgerAccountType::PLATFORM_PAYOUTS_PAYABLE);
        $wallet = $owned->get(LedgerAccountType::USER_WALLET->value)->balance ?? Money::ZERO;
        $debt = $owned->get(LedgerAccountType::USER_COMMISSION_RECEIVABLE->value)->balance ?? Money::ZERO;

        if (Money::compare($amount, $wallet) > 0) {
            throw new DomainException(ErrorCode::WALLET_INSUFFICIENT_BALANCE);
        }

        $withdrawable = $this->wallets->withdrawable($wallet, $debt);

        if (Money::compare($amount, $withdrawable) > 0) {
            throw new DomainException(ErrorCode::WALLET_EXCEEDS_WITHDRAWABLE, details: ['withdrawable' => $withdrawable]);
        }

        $nettable = Money::subtract($debt, CommissionSettlement::pendingTransferAmount($seller->id));

        return Money::isPositive($nettable) ? $nettable : Money::ZERO;
    }
}
