<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage\Finance;

use App\Actions\Finance\ReviewWithdrawalAction;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\Finance\MarkWithdrawalPaidRequest;
use App\Http\Requests\Manage\Finance\RejectFinanceRequest;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Finance\FinanceQueues;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Withdrawals waiting to be transferred. Only staff who can pay them see
 * the full IBAN; everyone else sees it masked.
 */
class WithdrawalController extends Controller
{
    public function index(Request $request, FinanceQueues $queues): View
    {
        $status = WithdrawalStatus::tryFrom((string) $request->query('status')) ?? WithdrawalStatus::PENDING;

        return view('admin.finance.withdrawals', [
            'status' => $status,
            'withdrawals' => $queues->withdrawals($status),
            'canManage' => $this->admin($request)->can('finance.manage'),
        ]);
    }

    public function pay(MarkWithdrawalPaidRequest $request, Withdrawal $withdrawal, ReviewWithdrawalAction $review): RedirectResponse
    {
        $review->markPaid($this->admin($request), $withdrawal, $request->transferReference());

        return back()->with('status', __('admin.finance.withdrawals.paid'));
    }

    public function reject(RejectFinanceRequest $request, Withdrawal $withdrawal, ReviewWithdrawalAction $review): RedirectResponse
    {
        $review->reject($this->admin($request), $withdrawal, $request->reason());

        return back()->with('status', __('admin.finance.withdrawals.rejected'));
    }

    private function admin(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
