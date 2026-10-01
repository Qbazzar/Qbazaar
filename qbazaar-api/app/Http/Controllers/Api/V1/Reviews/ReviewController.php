<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reviews;

use App\Actions\Reviews\CreateReviewAction;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reviews\CreateReviewRequest;
use App\Http\Resources\Api\V1\Reviews\ReviewResource;
use App\Models\Ad;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Seller reviews. Who may review is decided by ReviewEligibilityService;
 * listing is public.
 */
class ReviewController extends Controller
{
    private const PER_PAGE = 15;

    /**
     * POST /api/v1/ads/{ad}/reviews — leave a review for the ad's seller.
     *
     * @throws DomainException
     */
    public function store(CreateReviewRequest $request, string $adId, CreateReviewAction $createReview): JsonResponse
    {
        $ad = Ad::query()->find($adId);
        if ($ad === null) {
            throw new DomainException(ErrorCode::AD_NOT_FOUND);
        }

        /** @var User $reviewer */
        $reviewer = $request->user();

        $comment = $request->validated('comment');

        $review = $createReview->execute(
            $reviewer,
            $ad,
            (int) $request->validated('rating'),
            is_string($comment) ? $comment : null,
        );

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
        // A review outlives a deleted ad or reviewer account, so both are
        // loaded with their soft-deleted rows.
        $reviews = Review::query()
            ->where('seller_id', $userId)
            ->with([
                'reviewer' => fn ($query) => $query->withTrashed()->select(['id', 'full_name', 'avatar_url']),
                'ad' => fn ($query) => $query->withTrashed()->select(['id', 'title']),
            ])
            ->latest()
            ->paginate(self::PER_PAGE);

        return ReviewResource::collection($reviews);
    }
}
