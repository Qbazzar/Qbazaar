<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage\Finance;

use App\Actions\Orders\ResolveOrderDisputeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\Finance\ResolveDisputeRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\Finance\FinanceQueues;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Orders a buyer reported a problem with, waiting for an admin's ruling.
 * Staff never complete or release individual sales outside a dispute.
 */
class DisputeController extends Controller
{
    public function index(FinanceQueues $queues): View
    {
        return view('admin.finance.disputes', [
            'orders' => $queues->openDisputes(),
        ]);
    }

    public function resolve(ResolveDisputeRequest $request, Order $order, ResolveOrderDisputeAction $resolve): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $resolve($admin, $order, $request->resolution(), $request->note());

        return back()->with('status', __('admin.finance.disputes.resolved'));
    }
}
