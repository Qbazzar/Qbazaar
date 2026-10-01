<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Review;
use App\Models\User;
use App\Services\Reviews\ReviewEligibilityService;
use Illuminate\Database\UniqueConstraintViolationException;

class CreateReviewAction
{
    public function __construct(
        private readonly ReviewEligibilityService $eligibility,
    ) {}

    /**
     * @throws DomainException
     */
    public function execute(User $reviewer, Ad $ad, int $rating, ?string $comment): Review
    {
        $this->eligibility->ensureCanReview($reviewer, $ad);

        try {
            $review = Review::query()->create([
                'ad_id' => $ad->id,
                'seller_id' => $ad->user_id,
                'reviewer_id' => $reviewer->id,
                'rating' => $rating,
                'comment' => $comment,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A double-submit that passed the eligibility check concurrently.
            throw new DomainException(ErrorCode::REVIEW_ALREADY_EXISTS);
        }

        return $review->setRelation('reviewer', $reviewer)->setRelation('ad', $ad);
    }
}
