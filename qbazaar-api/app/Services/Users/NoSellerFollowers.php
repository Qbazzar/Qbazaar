<?php

declare(strict_types=1);

namespace App\Services\Users;

/**
 * Bound until follows exist (BE-14.28): nobody follows anyone yet.
 */
class NoSellerFollowers implements SellerFollowers
{
    public function chunkFollowerIds(string $sellerId, int $chunkSize, callable $callback): void {}
}
