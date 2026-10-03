<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage\Finance;

use App\Actions\Finance\ReviewSettlementAction;
use App\Enums\SettlementStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\Finance\RejectFinanceRequest;
use App\Models\CommissionSettlement;
use App\Models\User;
use App\Services\Finance\FinanceQueues;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Commission settlements paid by bank transfer, waiting for finance staff
 * to match them against the bank statement.
 */
class SettlementController extends Controller
{
    public function index(Request $request, FinanceQueues $queues): View
    {
        $status = SettlementStatus::tryFrom((string) $request->query('status')) ?? SettlementStatus::PENDING;

        return view('admin.finance.settlements', [
            'status' => $status,
            'settlements' => $queues->settlements($status),
        ]);
    }

    /**
     * The transfer receipt, streamed from the private disk to staff only.
     */
    public function proof(CommissionSettlement $settlement): StreamedResponse
    {
        $proof = $settlement->proof() ?? abort(404);

        return Storage::disk($proof->disk)->response($proof->getPathRelativeToRoot(), $proof->file_name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function approve(Request $request, CommissionSettlement $settlement, ReviewSettlementAction $review): RedirectResponse
    {
        $review->approve($this->admin($request), $settlement);

        return back()->with('status', __('admin.finance.settlements.approved'));
    }

    public function reject(RejectFinanceRequest $request, CommissionSettlement $settlement, ReviewSettlementAction $review): RedirectResponse
    {
        $review->reject($this->admin($request), $settlement, $request->reason());

        return back()->with('status', __('admin.finance.settlements.rejected'));
    }

    private function admin(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
