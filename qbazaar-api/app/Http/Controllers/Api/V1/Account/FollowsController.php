<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Social\RemoveFollowerAction;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Users\FollowListCollection;
use App\Models\User;
use App\Services\Users\FollowQueries;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The signed-in user's followers and the accounts they follow.
 *
 * @group Account
 */
class FollowsController extends Controller
{
    public function __construct(
        private readonly FollowQueries $queries,
    ) {}

    /**
     * GET /api/v1/account/followers — cursor-paginated, newest first.
     *
     * @authenticated
     */
    public function followers(Request $request): FollowListCollection
    {
        $me = $this->me($request);
        $page = $this->queries->followersOf($me);

        /** @var list<string> $followerIds */
        $followerIds = $page->getCollection()->pluck('follower_id')->all();

        return new FollowListCollection($page, 'follower', $this->queries->followedAmong($me, $followerIds));
    }

    /**
     * GET /api/v1/account/following — cursor-paginated, newest first.
     *
     * @authenticated
     */
    public function following(Request $request): FollowListCollection
    {
        $page = $this->queries->followingOf($this->me($request));

        /** @var array<string, true> $all */
        $all = $page->getCollection()->mapWithKeys(static fn ($follow): array => [$follow->followed_id => true])->all();

        return new FollowListCollection($page, 'followed', $all);
    }

    /**
     * DELETE /api/v1/account/followers/{user} — remove a follower. Idempotent.
     *
     * @authenticated
     *
     * @throws DomainException
     */
    public function removeFollower(Request $request, RemoveFollowerAction $removeFollower, string $user): Response
    {
        $follower = User::query()->find($user) ?? throw new DomainException(ErrorCode::USER_NOT_FOUND);

        $removeFollower($this->me($request), $follower);

        return response()->noContent();
    }

    private function me(Request $request): User
    {
        /** @var User $me */
        $me = $request->user();

        return $me;
    }
}
