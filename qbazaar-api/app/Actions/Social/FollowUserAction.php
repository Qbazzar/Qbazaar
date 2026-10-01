<?php

declare(strict_types=1);

namespace App\Actions\Social;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Services\Users\FollowGraph;

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
     * @throws DomainException
     */
    public function __invoke(User $follower, User $followed): void
    {
        if ($follower->id === $followed->id) {
            throw new DomainException(ErrorCode::FOLLOW_SELF_FORBIDDEN);
        }

        if ($follower->isBlockedEitherWay($followed)) {
            throw new DomainException(ErrorCode::FOLLOW_BLOCKED);
        }

        $this->graph->link($follower, $followed);
    }
}
