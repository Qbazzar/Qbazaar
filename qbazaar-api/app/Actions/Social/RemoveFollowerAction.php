<?php

declare(strict_types=1);

namespace App\Actions\Social;

use App\Models\User;
use App\Services\Users\FollowGraph;

/**
 * Removes someone from the owner's followers without blocking them; they may
 * follow again later.
 */
class RemoveFollowerAction
{
    public function __construct(
        private readonly FollowGraph $graph,
    ) {}

    public function __invoke(User $owner, User $follower): void
    {
        $this->graph->unlink($follower, $owner);
    }
}
