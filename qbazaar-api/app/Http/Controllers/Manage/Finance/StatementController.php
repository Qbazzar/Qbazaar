<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage\Finance;

use App\Http\Controllers\Controller;
use App\Models\CommissionSettlement;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Ledger\WalletService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

/**
 * A user's balances and ledger statement, read-only.
 */
class StatementController extends Controller
{
    private const SIDE_LIST_LIMIT = 3;

    public function show(User $user, WalletService $wallets): View
    {
        $user->loadMissing('avatarMedia');

        return view('admin.finance.statement', [
            'user' => $user,
            'summary' => $wallets->summary($user->id),
            'entries' => $wallets->statement($user->id, null, null, (int) config('qbazaar.wallet.statement_per_page')),
            'settlements' => rescue(fn () => $this->latestSettlements($user)),
            'withdrawals' => rescue(fn () => $this->latestWithdrawals($user)),
        ]);
    }

    /**
     * Served by commission_settlements_user_created_idx.
     *
     * @return Collection<int, CommissionSettlement>
     */
    private function latestSettlements(User $user): Collection
    {
        return CommissionSettlement::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(self::SIDE_LIST_LIMIT)
            ->get(['id', 'amount', 'status', 'created_at']);
    }

    /**
     * Served by withdrawals_user_created_idx.
     *
     * @return Collection<int, Withdrawal>
     */
    private function latestWithdrawals(User $user): Collection
    {
        return Withdrawal::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(self::SIDE_LIST_LIMIT)
            ->get(['id', 'amount', 'status', 'created_at']);
    }
}
