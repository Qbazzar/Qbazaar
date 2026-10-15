<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage\Finance;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Admin\Ads\OrderDealBuilder;
use Illuminate\View\View;

/**
 * One order, read-only: its tracker, amounts, payment state and timeline.
 * Staff act on orders only through disputes, so there is no listing here;
 * the ad page and the disputes queue link to it.
 */
class OrderController extends Controller
{
    public function show(Order $order, OrderDealBuilder $deals): View
    {
        $order->load(['ad:id,title,status', 'buyer', 'seller', 'buyer.avatarMedia', 'seller.avatarMedia']);

        return view('admin.finance.order', [
            'order' => $order,
            'deal' => $deals->build($order),
        ]);
    }
}
