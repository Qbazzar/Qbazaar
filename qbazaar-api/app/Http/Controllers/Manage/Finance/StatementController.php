<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage\Finance;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Ledger\WalletService;
use Illuminate\View\View;

/**
 * A user's balances and ledger statement, read-only.
 */
class StatementController extends Controller
{
    public function show(User $user, WalletService $wallets): View
    {
        return view('admin.finance.statement', [
            'user' => $user,
            'summary' => $wallets->summary($user->id),
            'entries' => $wallets->statement($user->id, null, null, (int) config('qbazaar.wallet.statement_per_page')),
        ]);
    }
}
