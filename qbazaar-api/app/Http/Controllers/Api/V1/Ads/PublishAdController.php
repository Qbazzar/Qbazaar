<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ads;

use App\Actions\Ads\ModerateAdAction;
use App\Events\Ads\AdSubmittedForReview;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Ads\PublishAdRequest;
use App\Http\Resources\Api\V1\Ads\AdResource;
use App\Models\Ad;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/ads/{id}/publish — submit a draft for review.
 *
 * Every ad now requires manual admin approval before going live: publishing
 * always transitions DRAFT → PENDING and fires AdSubmittedForReview, which
 * notifies reviewers via the panel bell. Auto-moderation still runs so the
 * notification can hint which (if any) rules fired. An admin then approves the
 * ad in the panel (PENDING → ACTIVE) — see AdResource's approve action.
 *
 * Returns 200 with the updated AdResource; the client inspects `data.status`
 * (`pending`) to render the "we're reviewing your ad" UX.
 *
 * @group Ads
 */
class PublishAdController extends Controller
{
    public function __construct(
        private readonly ModerateAdAction $moderate,
    ) {}

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

        // Run moderation for triage hints only — the ad goes to manual review
        // regardless, so a "clean" result no longer auto-publishes.
        $result = ($this->moderate)($ad);

        $ad->holdForReview();
        AdSubmittedForReview::dispatch($ad, $result);

        $ad->load(['user', 'category', 'location', 'media']);

        return response()->json((new AdResource($ad))->toArray($request));
    }
}
