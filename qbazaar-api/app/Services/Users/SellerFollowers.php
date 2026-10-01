<?php

declare(strict_types=1);

namespace App\Services\Users;

/**
 * Who follows a seller. The new-ad alert fans out through this contract so
 * it works unchanged once follows exist (BE-14.28 binds the implementation).
 */
interface SellerFollowers
{
    /**
     * Calls $callback with consecutive chunks of follower user ids.
     *
     * @param callable(list<string>): void $callback
     */
    public function chunkFollowerIds(string $sellerId, int $chunkSize, callable $callback): void;
}
