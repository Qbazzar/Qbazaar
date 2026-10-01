<?php

declare(strict_types=1);

namespace App\Actions\Social;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Services\Users\FollowGraph;
use Illuminate\Support\Facades\DB;

/**
 * Follows another user. Idempotent; refused for yourself and whenever either
 * side has blocked the other.
 */
class FollowUserAction
{
    public function __construct(
        private readonly FollowGraph $graph,
    ) {}

    /**
     * The block check is a locking read in the same transaction as the
     * insert, so a block committed at the same moment either is seen here or
     * waits for this follow and then removes it.
     *
     * @throws DomainException
     */
    public function __invoke(User $follower, User $followed): void
    {
        if ($follower->id === $followed->id) {
            throw new DomainException(ErrorCode::FOLLOW_SELF_FORBIDDEN);
        }

        DB::transaction(function () use ($follower, $followed): void {
            if ($follower->isBlockedEitherWay($followed, lock: true)) {
                throw new DomainException(ErrorCode::FOLLOW_BLOCKED);
            }

            $this->graph->link($follower, $followed);
        });
    }
}
