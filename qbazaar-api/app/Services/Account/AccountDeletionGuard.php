<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Enums\LedgerAccountType;
use App\Enums\OrderStatus;
use App\Enums\WithdrawalStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\CommissionSettlement;
use App\Models\Order;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Ledger\LedgerAccounts;
use App\Support\Money;

/**
 * Decides whether an account may be erased. Four things stop it: commission
 * the user still owes the platform (erasing would write the debt off), an
 * order still in progress (erasing would leave the other side with a trade
 * nobody can finish), money still in their wallet (erasing would lose it)
 * and a withdrawal or settlement waiting for review (erasing would orphan
 * it). Checked when the deletion is requested and again by DeleteAccountJob
 * right before erasing, since any of them can appear during the grace period.
 */
class AccountDeletionGuard
{
    public function __construct(
        private readonly LedgerAccounts $accounts,
    ) {}

    /**
     * @throws DomainException
     */
    public function ensureDeletable(User $user): void
    {
        $blocker = $this->blockerFor($user);

        if ($blocker !== null) {
            throw $blocker;
        }
    }

    /**
     * The refusal that applies to this user, or null when the account may go.
     */
    public function blockerFor(User $user): ?DomainException
    {
        return $this->debtBlocker($user)
            ?? $this->openOrderBlocker($user)
            ?? $this->walletBalanceBlocker($user)
            ?? $this->pendingPayoutBlocker($user);
    }

    private function debtBlocker(User $user): ?DomainException
    {
        $debt = $this->accounts->balanceOf(LedgerAccountType::USER_COMMISSION_RECEIVABLE, $user->id);

        if (! Money::isPositive($debt)) {
            return null;
        }

        return new DomainException(
            ErrorCode::ACCOUNT_DEBT_OUTSTANDING,
            __(ErrorCode::ACCOUNT_DEBT_OUTSTANDING->messageKey(), ['amount' => $debt]),
            ['amount' => $debt, 'currency' => $this->currency()],
        );
    }

    private function openOrderBlocker(User $user): ?DomainException
    {
        $orderId = $this->openOrderIdOf($user);

        return $orderId === null
            ? null
            : new DomainException(ErrorCode::ACCOUNT_HAS_OPEN_ORDER, details: ['order_id' => $orderId]);
    }

    /**
     * Money the platform still owes the user would vanish with the account,
     * so they have to withdraw it first.
     */
    private function walletBalanceBlocker(User $user): ?DomainException
    {
        $balance = $this->accounts->balanceOf(LedgerAccountType::USER_WALLET, $user->id);

        if (! Money::isPositive($balance)) {
            return null;
        }

        return new DomainException(
            ErrorCode::ACCOUNT_WALLET_NOT_EMPTY,
            __(ErrorCode::ACCOUNT_WALLET_NOT_EMPTY->messageKey(), ['amount' => $balance]),
            ['amount' => $balance, 'currency' => $this->currency()],
        );
    }

    /**
     * A withdrawal debits the wallet when it is requested, so one waiting for
     * review leaves the wallet at zero and needs its own check; a settlement
     * waiting for review would be left half done. Both are looked up by an
     * index (`pending_user_id` is unique, `withdrawals_user_status_idx`).
     */
    private function pendingPayoutBlocker(User $user): ?DomainException
    {
        $settlementId = CommissionSettlement::query()->where('pending_user_id', $user->id)->value('id');

        if ($settlementId !== null) {
            return new DomainException(ErrorCode::ACCOUNT_PAYOUT_PENDING, details: ['settlement_id' => (string) $settlementId]);
        }

        $withdrawalId = Withdrawal::query()
            ->where('user_id', $user->id)
            ->where('status', WithdrawalStatus::PENDING->value)
            ->value('id');

        return $withdrawalId === null
            ? null
            : new DomainException(ErrorCode::ACCOUNT_PAYOUT_PENDING, details: ['withdrawal_id' => (string) $withdrawalId]);
    }

    private function currency(): string
    {
        return (string) config('qbazaar.default_currency', 'QAR');
    }

    /**
     * Two lookups rather than one OR so each side is served by its own
     * `(buyer_id, …)` / `(seller_id, …)` index.
     */
    private function openOrderIdOf(User $user): ?string
    {
        foreach (['buyer_id', 'seller_id'] as $party) {
            $orderId = Order::query()
                ->where($party, $user->id)
                ->whereIn('status', OrderStatus::activeValues())
                ->value('id');

            if ($orderId !== null) {
                return (string) $orderId;
            }
        }

        return null;
    }
}
