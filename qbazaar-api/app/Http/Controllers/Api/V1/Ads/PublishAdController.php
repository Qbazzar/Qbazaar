<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ads;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Ads\PublishAdRequest;
use App\Http\Resources\Api\V1\Ads\AdResource;
use App\Models\Ad;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/ads/{id}/publish — flip a draft to ACTIVE.
 *
 * Wave A skips the PENDING moderation hop; we go DRAFT → ACTIVE directly so
 * sellers see their ads on the feed immediately. Auto-moderation will plug
 * in here without changing the controller's contract.
 *
 * @group Ads
 */
class PublishAdController extends Controller
{
    /**
     * @authenticated
     *
     * @throws DomainException
     */
    public function __invoke(PublishAdRequest $request, string $id): JsonResponse
    {
        $ad = Ad::query()->find($id);

        if ($ad === null) {
            throw new DomainException(ErrorCode::AD_NOT_FOUND);
        }

        $this->authorize('publish', $ad);

        $ad->publish();
        $ad->load(['user', 'category', 'location', 'media']);

        return response()->json((new AdResource($ad))->toArray($request));
    }
}
