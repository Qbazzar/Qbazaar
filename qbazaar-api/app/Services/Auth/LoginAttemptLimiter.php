<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Locks an account identifier after repeated failed sign-ins, whatever IP
 * they come from, so credential stuffing spread across many addresses still
 * stops after a few guesses. The per-IP `auth` route limiter covers the
 * opposite case.
 */
class LoginAttemptLimiter
{
    /**
     * @throws DomainException
     */
    public function ensureNotLockedOut(string $identifier): void
    {
        $key = $this->key($identifier);

        if (! RateLimiter::tooManyAttempts($key, (int) config('qbazaar.auth.max_login_attempts'))) {
            return;
        }

        throw new DomainException(
            ErrorCode::AUTH_RATE_LIMITED,
            details: ['retry_after' => RateLimiter::availableIn($key)],
        );
    }

    public function recordFailure(string $identifier): void
    {
        RateLimiter::hit($this->key($identifier), (int) config('qbazaar.auth.login_lockout_minutes') * 60);
    }

    public function clear(string $identifier): void
    {
        RateLimiter::clear($this->key($identifier));
    }

    private function key(string $identifier): string
    {
        return 'login-identifier:' . hash('sha256', mb_strtolower(trim($identifier)));
    }
}
