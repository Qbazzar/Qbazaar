<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Users;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * GET /users/{user}/public-profile: the public user card, the business
 * details of a business account (null otherwise), and what depends on who
 * is looking.
 */
class PublicProfileResource extends PublicUserResource
{
    public function __construct(
        User $user,
        private readonly bool $isFollowing,
    ) {
        parent::__construct($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'is_following' => $this->isFollowing,
            'business_profile' => $this->resource->isBusiness()
                ? (new BusinessProfileResource($this->resource, publicView: true))->toArray($request)
                : null,
        ];
    }
}
