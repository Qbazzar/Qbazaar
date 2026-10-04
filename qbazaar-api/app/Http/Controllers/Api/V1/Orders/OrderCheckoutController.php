<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Orders;

use App\Actions\Orders\CheckoutOrderAction;
use App\Actions\Orders\ShowCheckoutAction;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\CheckoutOrderRequest;
use App\Http\Resources\Api\V1\Orders\CheckoutResource;
use App\Http\Resources\Api\V1\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Checkout of an order by its buyer. The seller gets 403, anyone else
 * ORDER_001 (404).
 *
 * @group Orders
 */
class OrderCheckoutController extends Controller
{
    public function __construct(
        private readonly OrderQueries $orders,
    ) {}

    /**
     * GET /api/v1/orders/{id}/checkout
     *
     * @authenticated
     */
    public function show(Request $request, string $id, ShowCheckoutAction $show): JsonResponse
    {
        $order = $this->findOrFail($request, $id);
        $this->authorize('checkout', $order);

        return response()->json((new CheckoutResource($show($this->user($request), $order)))->resolve($request));
    }

    /**
     * POST /api/v1/orders/{id}/checkout
     *
     * @authenticated
     */
    public function store(CheckoutOrderRequest $request, string $id, CheckoutOrderAction $checkout): JsonResponse
    {
        $order = $this->findOrFail($request, $id);
        $this->authorize('checkout', $order);

        $order = $checkout($this->user($request), $order, $request->details());

        return response()->json((new OrderResource($order))->resolve($request));
    }

    private function findOrFail(Request $request, string $id): Order
    {
        return $this->orders->findForParticipant($id, $this->user($request))
            ?? throw new DomainException(ErrorCode::ORDER_NOT_FOUND);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
