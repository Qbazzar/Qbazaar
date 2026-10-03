<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Data\Ledger\WalletSummary;
use App\Enums\LedgerAccountType;
use App\Enums\LedgerTransactionType;
use App\Enums\PlatformSetting;
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
        $debt = $accounts->get(LedgerAccountType::USER_COMMISSION_RECEIVABLE->value)?->balance ?? Money::ZERO;

        return new WalletSummary(
            available: $accounts->get(LedgerAccountType::USER_WALLET->value)?->balance ?? Money::ZERO,
            commissionDebt: $debt,
            debtCeiling: $ceiling,
            canAcceptOrders: ! $this->isOverCeiling($debt, $ceiling),
        );
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
