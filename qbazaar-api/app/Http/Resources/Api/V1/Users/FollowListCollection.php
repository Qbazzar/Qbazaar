<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Users;

use App\Models\Follow;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of followers or followed accounts. Each row is the other user plus
 * whether the viewer follows them, resolved for the whole page beforehand.
 * The paginator keeps its Follow rows so the cursor is built from follow ids.
 */
class FollowListCollection extends ResourceCollection
{
    /**
     * @param CursorPaginator<int, Follow> $page
     * @param 'follower'|'followed' $counterpart
     * @param array<string, true> $viewerFollows ids of listed users the viewer follows
     */
    public function __construct(
        CursorPaginator $page,
        private readonly string $counterpart,
        private readonly array $viewerFollows,
    ) {
        parent::__construct($page);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return $this->collection
            ->map(fn (Follow $follow): array => $this->row($follow))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Follow $follow): array
    {
        /** @var User $user */
        $user = $follow->getRelation($this->counterpart);

        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'avatar_url' => $user->avatar_url,
            'account_type' => $user->account_type->value,
            'followers_count' => $user->followers_count,
            'is_following' => isset($this->viewerFollows[$user->id]),
            'followed_at' => $follow->created_at?->toIso8601String(),
        ];
    }
}
