<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reviews;

use App\Enums\OfferStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reviews\CreateReviewRequest;
use App\Http\Resources\Api\V1\Reviews\ReviewResource;
use App\Models\Ad;
use App\Models\Offer;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Seller reviews. A buyer may review a seller once per ad, and only after a
 * completed deal — proven by an ACCEPTED offer on that ad. Listing is public.
 */
class ReviewController extends Controller
{
    private const PER_PAGE = 15;

    /**
     * POST /api/v1/ads/{ad}/reviews — leave a review for the ad's seller.
     *
     * @throws DomainException
     */
    public function store(CreateReviewRequest $request, string $adId): JsonResponse
    {
        $ad = Ad::query()->find($adId);
        if ($ad === null) {
            throw new DomainException(ErrorCode::AD_NOT_FOUND);
        }

        /** @var User $reviewer */
        $reviewer = $request->user();

        if ($reviewer->id === $ad->user_id) {
            throw new DomainException(ErrorCode::REVIEW_OWN_AD);
        }

        // Eligibility: the reviewer must have closed a deal on this ad — i.e.
        // hold an accepted offer for it.
        $hasDeal = Offer::query()
            ->where('ad_id', $ad->id)
            ->where('buyer_id', $reviewer->id)
            ->where('status', OfferStatus::ACCEPTED->value)
            ->exists();

        if (! $hasDeal) {
            throw new DomainException(ErrorCode::REVIEW_NOT_ELIGIBLE);
        }

        $exists = Review::query()
            ->where('reviewer_id', $reviewer->id)
            ->where('ad_id', $ad->id)
            ->exists();

        if ($exists) {
            throw new DomainException(ErrorCode::REVIEW_ALREADY_EXISTS);
        }

        $review = new Review;
        $review->fill([
            'ad_id' => $ad->id,
            'seller_id' => $ad->user_id,
            'reviewer_id' => $reviewer->id,
            'rating' => (int) $request->validated('rating'),
            'comment' => $request->validated('comment'),
        ]);
        $review->save();

        $review->load(['reviewer', 'ad']);

        return response()
            ->json((new ReviewResource($review))->toArray($request))
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    /**
     * GET /api/v1/users/{user}/reviews — a seller's reviews, newest first.
     *
     * @unauthenticated
     */
    public function index(string $userId): AnonymousResourceCollection
    {
        $reviews = Review::query()
            ->where('seller_id', $userId)
            ->with(['reviewer', 'ad'])
            ->latest()
            ->paginate(self::PER_PAGE);

        return ReviewResource::collection($reviews);
    }
}
