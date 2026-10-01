<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\Follow;
use Illuminate\Support\Collection;

class FollowTableSellerFollowers implements SellerFollowers
{
    public function chunkFollowerIds(string $sellerId, int $chunkSize, callable $callback): void
    {
        // Walks follows_followed_idx (followed_id, id) so each chunk is an index range scan.
        Follow::query()
            ->where('followed_id', $sellerId)
            ->select(['id', 'follower_id'])
            ->chunkById($chunkSize, function (Collection $follows) use ($callback): void {
                /** @var list<string> $ids */
                $ids = $follows->pluck('follower_id')->values()->all();
                $callback($ids);
            });
    }
}
