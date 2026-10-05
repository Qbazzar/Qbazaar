<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Data\Ledger\LockedWallet;
use App\Data\Ledger\WalletSummary;
use App\Enums\LedgerAccountType;
use App\Enums\LedgerTransactionType;
use App\Enums\PlatformSetting;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Services\Settings\SettingsService;
use App\Support\Money;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * A user's view of the ledger: their two accounts (the wallet the platform
 * owes them and the commission they owe the platform) and the statement of
 * every line posted to them.
 */
class WalletService
{
    /** The user-facing accounts, in the order a statement filter offers them. */
    public const array USER_ACCOUNTS = [
        LedgerAccountType::USER_WALLET,
        LedgerAccountType::USER_COMMISSION_RECEIVABLE,
    ];

    public function __construct(
        private readonly LedgerAccounts $accounts,
        private readonly SettingsService $settings,
    ) {}

    public function summary(string $userId): WalletSummary
    {
        $accounts = $this->accounts->ofUser($userId);
        $ceiling = $this->settings->decimal(PlatformSetting::COMMISSION_DEBT_CEILING);
        $debt = $accounts->get(LedgerAccountType::USER_COMMISSION_RECEIVABLE->value)->balance ?? Money::ZERO;
        $available = $accounts->get(LedgerAccountType::USER_WALLET->value)->balance ?? Money::ZERO;

        return new WalletSummary(
            available: $available,
            withdrawable: $this->withdrawable($available, $debt),
            commissionDebt: $debt,
            debtCeiling: $ceiling,
            canAcceptOrders: ! $this->isOverCeiling($debt, $ceiling),
        );
    }

    /**
     * What a seller can take out of the wallet: the balance minus the
     * commission they still owe, so a debt cannot be withdrawn away.
     */
    public function withdrawable(string $balance, string $debt): string
    {
        $left = Money::subtract($balance, $debt);

        return Money::isPositive($left) ? $left : Money::ZERO;
    }

    /**
     * Locks the user's accounts (and one platform account the caller is
     * about to post to) and refuses a spend larger than the wallet or than
     * the withdrawable amount. Call it inside the transaction that posts the
     * spend, so concurrent spends are checked one after the other.
     */
    public function lockForSpend(string $userId, string $amount, ?LedgerAccountType $alongside = null): LockedWallet
    {
        $owned = $this->accounts->lockUserAccounts($userId, $alongside);
        $wallet = $owned->get(LedgerAccountType::USER_WALLET->value)->balance ?? Money::ZERO;
        $debt = $owned->get(LedgerAccountType::USER_COMMISSION_RECEIVABLE->value)->balance ?? Money::ZERO;

        if (Money::compare($amount, $wallet) > 0) {
            throw new DomainException(ErrorCode::WALLET_INSUFFICIENT_BALANCE);
        }

        $withdrawable = $this->withdrawable($wallet, $debt);

        if (Money::compare($amount, $withdrawable) > 0) {
            throw new DomainException(ErrorCode::WALLET_EXCEEDS_WITHDRAWABLE, details: ['withdrawable' => $withdrawable]);
        }

        return new LockedWallet($wallet, $debt, $withdrawable);
    }

    /**
     * A seller whose unpaid commission has reached the admin-set ceiling
     * takes no new orders until they settle.
     */
    public function canAcceptOrders(string $sellerId): bool
    {
        return ! $this->isOverCeiling(
            $this->accounts->balanceOf(LedgerAccountType::USER_COMMISSION_RECEIVABLE, $sellerId),
            $this->settings->decimal(PlatformSetting::COMMISSION_DEBT_CEILING),
        );
    }

    /**
     * @return CursorPaginator<int, LedgerEntry>
     */
    public function statement(string $userId, ?LedgerTransactionType $type, ?LedgerAccountType $account, int $perPage): CursorPaginator
    {
        $accountIds = $this->accounts->ofUser($userId)
            ->filter(fn (LedgerAccount $owned): bool => $account === null || $owned->type === $account)
            ->pluck('id')
            ->all();

        return LedgerEntry::query()
            ->whereIn('account_id', $accountIds)
            ->when($type !== null, fn (Builder $query) => $query->whereHas(
                'transaction',
                fn (Builder $transaction) => $transaction->where('type', $type?->value),
            ))
            ->with(['transaction', 'account'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    private function isOverCeiling(string $debt, string $ceiling): bool
    {
        return Money::isPositive($debt) && Money::compare($debt, $ceiling) >= 0;
    }
}
