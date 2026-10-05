<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Enums\LedgerAccountType;
use App\Enums\OrderStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Order;
use App\Models\User;
use App\Services\Ledger\LedgerAccounts;
use App\Support\Money;

/**
 * Decides whether an account may be erased. Three things stop it: commission
 * the user still owes the platform (erasing would write the debt off), an
 * order still in progress (erasing would leave the other side with a trade
 * nobody can finish) and money still in their wallet (erasing would lose it). Checked when the deletion is requested and again by
 * DeleteAccountJob right before erasing, since either can appear during the
 * grace period.
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
     * review leaves the wallet at zero and needs its own check. Withdrawals
     * and settlements arrive with the M1b settlements work; until then there
     * is nothing pending to look for. Wire them in here.
     */
    // @phpstan-ignore return.unusedType (nothing pending to find until #211's models exist)
    private function pendingPayoutBlocker(User $user): ?DomainException
    {
        return null;
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
