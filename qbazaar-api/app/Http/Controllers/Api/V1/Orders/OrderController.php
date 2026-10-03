<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Orders;

use App\Actions\Orders\CancelOrderAction;
use App\Actions\Orders\ConfirmOrderHandoverAction;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\CancelOrderRequest;
use App\Http\Requests\Api\V1\Orders\ListOrdersRequest;
use App\Http\Resources\Api\V1\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Orders for their two participants. Anyone else gets ORDER_001 (404), so
 * order ids cannot be probed.
 *
 * @group Orders
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderQueries $orders,
    ) {}

    /**
     * GET /api/v1/account/orders — purchases (`role=buyer`) or sales (`role=seller`), newest first.
     *
     * @authenticated
     */
    public function index(ListOrdersRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return OrderResource::collection($this->orders->forParticipant(
            $user,
            $request->role(),
            $request->status(),
            (int) config('qbazaar.orders.per_page'),
        ));
    }

    /**
     * GET /api/v1/orders/{id}
     *
     * @authenticated
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $order = $this->findOrFail($request, $id);
        $this->authorize('view', $order);

        return $this->respond($request, $order);
    }

    /**
     * POST /api/v1/orders/{id}/confirm-handover (seller)
     *
     * @authenticated
     */
    public function confirmHandover(Request $request, string $id, ConfirmOrderHandoverAction $confirm): JsonResponse
    {
        $order = $this->findOrFail($request, $id);
        $this->authorize('confirmHandover', $order);

        return $this->respond($request, $confirm($this->user($request), $order));
    }

    /**
     * POST /api/v1/orders/{id}/cancel (buyer or seller)
     *
     * @authenticated
     */
    public function cancel(CancelOrderRequest $request, string $id, CancelOrderAction $cancel): JsonResponse
    {
        $order = $this->findOrFail($request, $id);
        $this->authorize('cancel', $order);

        return $this->respond($request, $cancel($this->user($request), $order, $request->reason()));
    }

    private function respond(Request $request, Order $order): JsonResponse
    {
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
