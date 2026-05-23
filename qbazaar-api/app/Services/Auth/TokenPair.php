<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * Immutable value object representing one access+refresh pair issued to a client.
 * Returned by RefreshTokenService::issue() and consumed by the controllers /
 * Resources. Kept as a plain class (not Spatie\Data) to keep this layer
 * framework-light and easy to type-hint.
 */
final readonly class TokenPair
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
    ) {}
}
