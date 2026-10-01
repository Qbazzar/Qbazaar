<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Users;

use App\Actions\Social\FollowUserAction;
use App\Actions\Social\UnfollowUserAction;
use App\Enums\UserStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST / DELETE /api/v1/users/{user}/follow
 *
 * @group Users
 */
class FollowController extends Controller
{
    /**
     * Follow a user. Idempotent.
     *
     * @authenticated
     *
     * @throws DomainException
     */
    public function store(Request $request, FollowUserAction $follow, string $user): JsonResponse
    {
        $target = $this->activeUser($user);

        $follow($this->follower($request), $target);

        return $this->state($target, following: true);
    }

    /**
     * Stop following a user. Idempotent.
     *
     * @authenticated
     *
     * @throws DomainException
     */
    public function destroy(Request $request, UnfollowUserAction $unfollow, string $user): JsonResponse
    {
        $target = $this->activeUser($user);

        $unfollow($this->follower($request), $target);

        return $this->state($target, following: false);
    }

    private function follower(Request $request): User
    {
        /** @var User $viewer */
        $viewer = $request->user();

        return $viewer;
    }

    /**
     * @throws DomainException
     */
    private function activeUser(string $id): User
    {
        return User::query()->whereKey($id)->where('status', UserStatus::ACTIVE->value)->first()
            ?? throw new DomainException(ErrorCode::USER_NOT_FOUND);
    }

    private function state(User $target, bool $following): JsonResponse
    {
        return response()->json([
            'following' => $following,
            'user_id' => $target->id,
            'followers_count' => (int) User::query()->whereKey($target->id)->value('followers_count'),
        ]);
    }
}
