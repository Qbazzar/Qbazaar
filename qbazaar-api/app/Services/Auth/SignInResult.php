<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;

/**
 * A sign-in either finishes with a token pair, or stops at a new-device
 * challenge that the client completes at POST /auth/device/verify.
 */
final readonly class SignInResult
{
    private function __construct(
        public User $user,
        public ?TokenPair $tokens,
        public ?DeviceChallenge $challenge,
        public bool $registered,
    ) {}

    public static function authenticated(User $user, TokenPair $tokens): self
    {
        return new self($user, $tokens, null, false);
    }

    public static function registered(User $user, TokenPair $tokens): self
    {
        return new self($user, $tokens, null, true);
    }

    public static function challenged(User $user, DeviceChallenge $challenge): self
    {
        return new self($user, null, $challenge, false);
    }
}
