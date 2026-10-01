<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Users;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * GET /users/{user}/public-profile: the public user card plus what depends
 * on who is looking.
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
        ];
    }
}
