<?php

declare(strict_types=1);

namespace App\Actions\Social;

use App\Models\User;
use App\Services\Users\FollowGraph;

/**
 * Stops following a user. Idempotent so retries after a network error succeed.
 */
class UnfollowUserAction
{
    public function __construct(
        private readonly FollowGraph $graph,
    ) {}

    public function __invoke(User $follower, User $followed): void
    {
        $this->graph->unlink($follower, $followed);
    }
}
