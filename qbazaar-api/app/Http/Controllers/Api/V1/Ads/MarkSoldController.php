<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ads;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Ads\AdResource;
use App\Models\Ad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v1/ads/{id}/mark-sold — flip ACTIVE / EXPIRED ad to SOLD.
 *
 * @group Ads
 */
class MarkSoldController extends Controller
{
    /**
     * @authenticated
     *
     * @throws DomainException
     */
    public function __invoke(Request $request, string $id): JsonResponse
    {
        $ad = Ad::query()->find($id);

        if ($ad === null) {
            throw new DomainException(ErrorCode::AD_NOT_FOUND);
        }

        $this->authorize('mark-sold', $ad);

        $ad->markSold();
        $ad->load(['user', 'category', 'location', 'media']);

        return response()->json((new AdResource($ad))->toArray($request));
    }
}
