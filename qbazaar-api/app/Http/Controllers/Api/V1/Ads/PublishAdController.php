<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ads;

use App\Actions\Ads\PublishAdAction;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Ads\PublishAdRequest;
use App\Http\Resources\Api\V1\Ads\AdResource;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/ads/{id}/publish — submit an ad for admin review.
 *
 * The ad moves to PENDING and the reviewers are notified; the client reads
 * `data.status` to show the "we're reviewing your ad" state.
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
    public function __invoke(PublishAdRequest $request, PublishAdAction $publish, string $id): JsonResponse
    {
        $ad = Ad::query()->find($id);

        if ($ad === null) {
            throw new DomainException(ErrorCode::AD_NOT_FOUND);
        }

        $this->authorize('publish', $ad);

        /** @var User $seller */
        $seller = $request->user();

        $ad = $publish($ad, $seller);
        $ad->load(['user', 'category', 'location', 'media']);

        return response()->json((new AdResource($ad))->toArray($request));
    }
}
