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
 * Decides whether an account may be erased. Two things stop it: commission
 * the user still owes the platform (erasing would write the debt off) and an
 * order still in progress (erasing would leave the other side with a trade
 * nobody can finish). Checked when the deletion is requested and again by
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
        $debt = $this->accounts->balanceOf(LedgerAccountType::USER_COMMISSION_RECEIVABLE, $user->id);

        if (Money::isPositive($debt)) {
            return new DomainException(
                ErrorCode::ACCOUNT_DEBT_OUTSTANDING,
                __(ErrorCode::ACCOUNT_DEBT_OUTSTANDING->messageKey(), ['amount' => $debt]),
                ['amount' => $debt, 'currency' => (string) config('qbazaar.default_currency', 'QAR')],
            );
        }

        $openOrderId = $this->openOrderIdOf($user);

        if ($openOrderId !== null) {
            return new DomainException(ErrorCode::ACCOUNT_HAS_OPEN_ORDER, details: ['order_id' => $openOrderId]);
        }

        return null;
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
