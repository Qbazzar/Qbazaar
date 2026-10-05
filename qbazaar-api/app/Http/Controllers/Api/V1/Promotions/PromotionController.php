<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Promotions;

use App\Actions\Promotions\PurchasePromotionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Promotions\PurchasePromotionRequest;
use App\Http\Resources\Api\V1\Promotions\AdPromotionResource;
use App\Http\Resources\Api\V1\Promotions\PromotionOfferResource;
use App\Models\User;
use App\Services\Promotions\PromotionCatalog;
use App\Services\Promotions\PromotionQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Paid promotion: the catalogue, buying one for an ad, and the buyer's history.
 *
 * @group Promotions
 */
class PromotionController extends Controller
{
    /**
     * GET /api/v1/promotions — every promotion type with its current price and duration.
     *
     * @unauthenticated
     */
    public function index(Request $request, PromotionCatalog $catalog): JsonResponse
    {
        return response()->json(PromotionOfferResource::collection($catalog->all())->resolve($request));
    }

    /**
     * POST /api/v1/ads/{id}/promotions — the ad's owner buys a promotion.
     * Paid from the wallet it starts at once (201, status active); by bank
     * transfer it waits for an admin (201, status pending_payment).
     *
     * @authenticated
     */
    public function store(PurchasePromotionRequest $request, string $id, PurchasePromotionAction $purchase): JsonResponse
    {
        $promotion = $purchase(
            $this->user($request),
            $id,
            $request->type(),
            $request->paymentMethod(),
            $request->transferReference(),
        );

        return response()->json((new AdPromotionResource($promotion))->resolve($request), 201);
    }

    /**
     * GET /api/v1/account/promotions — the caller's promotions, newest first, cursor paginated.
     *
     * @authenticated
     */
    public function mine(Request $request, PromotionQueries $promotions): AnonymousResourceCollection
    {
        return AdPromotionResource::collection(
            $promotions->forOwner($this->user($request), (int) config('qbazaar.promotions.per_page')),
        );
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
