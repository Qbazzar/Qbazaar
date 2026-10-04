<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\PurchaseRequests;

use App\Actions\PurchaseRequests\AcceptPurchaseRequestAction;
use App\Actions\PurchaseRequests\CancelPurchaseRequestAction;
use App\Actions\PurchaseRequests\CreatePurchaseRequestAction;
use App\Actions\PurchaseRequests\RejectPurchaseRequestAction;
use App\Actions\PurchaseRequests\UpdatePurchaseRequestAction;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PurchaseRequests\SavePurchaseRequestRequest;
use App\Http\Resources\Api\V1\PurchaseRequests\PurchaseRequestResource;
use App\Models\Ad;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Buy Now" requests inside the chat. Anyone but the buyer and the seller
 * gets PURCHASE_001 (404), so request ids cannot be probed.
 *
 * @group Purchase requests
 */
class PurchaseRequestController extends Controller
{
    /**
     * POST /api/v1/ads/{id}/purchase-requests (buyer)
     *
     * @authenticated
     */
    public function store(SavePurchaseRequestRequest $request, string $id, CreatePurchaseRequestAction $create): JsonResponse
    {
        $ad = Ad::query()->find($id) ?? throw new DomainException(ErrorCode::AD_NOT_FOUND);

        $purchaseRequest = $create($this->user($request), $ad, $request->quantity(), $request->note());

        return $this->respond($request, $purchaseRequest, Response::HTTP_CREATED);
    }

    /**
     * PUT /api/v1/purchase-requests/{id} (buyer, while pending)
     *
     * @authenticated
     */
    public function update(SavePurchaseRequestRequest $request, string $id, UpdatePurchaseRequestAction $update): JsonResponse
    {
        $purchaseRequest = $this->findOrFail($request, $id);
        $this->authorize('update', $purchaseRequest);

        return $this->respond($request, $update($this->user($request), $purchaseRequest, $request->quantity(), $request->note()));
    }

    /**
     * POST /api/v1/purchase-requests/{id}/accept (seller)
     *
     * @authenticated
     */
    public function accept(Request $request, string $id, AcceptPurchaseRequestAction $accept): JsonResponse
    {
        $purchaseRequest = $this->findOrFail($request, $id);
        $this->authorize('accept', $purchaseRequest);

        return $this->respond($request, $accept($this->user($request), $purchaseRequest));
    }

    /**
     * POST /api/v1/purchase-requests/{id}/reject (seller)
     *
     * @authenticated
     */
    public function reject(Request $request, string $id, RejectPurchaseRequestAction $reject): JsonResponse
    {
        $purchaseRequest = $this->findOrFail($request, $id);
        $this->authorize('reject', $purchaseRequest);

        return $this->respond($request, $reject($this->user($request), $purchaseRequest));
    }

    /**
     * POST /api/v1/purchase-requests/{id}/cancel (buyer)
     *
     * @authenticated
     */
    public function cancel(Request $request, string $id, CancelPurchaseRequestAction $cancel): JsonResponse
    {
        $purchaseRequest = $this->findOrFail($request, $id);
        $this->authorize('cancel', $purchaseRequest);

        return $this->respond($request, $cancel($this->user($request), $purchaseRequest));
    }

    private function respond(Request $request, PurchaseRequest $purchaseRequest, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json((new PurchaseRequestResource($purchaseRequest))->resolve($request), $status);
    }

    private function findOrFail(Request $request, string $id): PurchaseRequest
    {
        $purchaseRequest = PurchaseRequest::query()->find($id);

        if ($purchaseRequest === null || ! $purchaseRequest->isParticipant($this->user($request))) {
            throw new DomainException(ErrorCode::PURCHASE_REQUEST_NOT_FOUND);
        }

        return $purchaseRequest;
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
