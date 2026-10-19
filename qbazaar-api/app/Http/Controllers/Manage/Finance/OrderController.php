<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage\Finance;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Admin\Ads\OrderDealBuilder;
use App\Services\Finance\OrderDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Orders, read-only: the list with a status filter and an order-id lookup
 * (AD-17.7), and one order's tracker, amounts, payment state and timeline.
 * Staff act on orders only through disputes.
 */
class OrderController extends Controller
{
    public function index(Request $request, OrderDirectory $orders): View
    {
        $status = OrderStatus::tryFrom($request->string('status')->toString());
        $search = trim($request->string('q')->toString());
        $orderId = Str::isUlid($search) ? Str::lower($search) : null;

        return view('admin.finance.orders', [
            'orders' => $orders->latest($status, $orderId),
            'status' => $status,
            'statusOptions' => $orders->statusOptions(),
            'search' => $search,
            'searchIgnored' => $search !== '' && $orderId === null,
        ]);
    }

    public function show(Order $order, OrderDealBuilder $deals): View
    {
        $order->load(['ad:id,title,status', 'buyer', 'seller', 'buyer.avatarMedia', 'seller.avatarMedia']);

        return view('admin.finance.order', [
            'order' => $order,
            'deal' => $deals->build($order),
        ]);
    }
}
