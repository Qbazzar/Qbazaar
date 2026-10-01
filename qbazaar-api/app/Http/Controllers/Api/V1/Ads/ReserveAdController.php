<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ads;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Ads\AdResource;
use App\Models\Ad;
use App\Services\Ads\AdLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST / DELETE /api/v1/ads/{id}/reserve — the seller holds a live ad for a buyer.
 *
 * @group Ads
 */
class ReserveAdController extends Controller
{
    public function __construct(
        private readonly AdLifecycleService $lifecycle,
    ) {}

    /**
     * @authenticated
     *
     * @throws DomainException
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $ad = $this->authorizedAd($id);

        return $this->respond($request, $this->lifecycle->reserve($ad));
    }

    /**
     * @authenticated
     *
     * @throws DomainException
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $ad = $this->authorizedAd($id);

        return $this->respond($request, $this->lifecycle->releaseReservation($ad));
    }

    /**
     * @throws DomainException
     */
    private function authorizedAd(string $id): Ad
    {
        $ad = Ad::query()->find($id) ?? throw new DomainException(ErrorCode::AD_NOT_FOUND);

        $this->authorize('reserve', $ad);

        return $ad;
    }

    private function respond(Request $request, Ad $ad): JsonResponse
    {
        $ad->load(['user', 'category', 'location', 'media']);

        return response()->json((new AdResource($ad))->toArray($request));
    }
}
