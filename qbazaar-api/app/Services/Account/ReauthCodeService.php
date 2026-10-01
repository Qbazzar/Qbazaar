<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Notifications\Account\ReauthCodeNotification;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Step-up check before sensitive account changes (email, phone).
 *
 * Sign-in is passwordless, so there is no password to re-enter; instead the
 * user proves control of the current email with a fresh one-time code. A
 * token's age is no proof of a recent sign-in either, because refresh
 * rotation mints new access tokens every 15 minutes.
 *
 * The code is hashed in the cache, expires, allows a few attempts and is
 * spent on first success.
 */
class ReauthCodeService
{
    private const int LENGTH = 6;

    /**
     * Emails a new code to the user's current address and returns how long
     * it stays valid, in seconds.
     */
    public function issue(User $user): int
    {
        $cooldown = (int) config('qbazaar.account.reauth_code_cooldown_seconds');

        if (! Cache::add($this->key($user, 'cooldown'), true, $cooldown)) {
            throw new DomainException(ErrorCode::AUTH_RATE_LIMITED);
        }

        $ttl = (int) config('qbazaar.account.reauth_code_ttl_minutes') * 60;
        $code = str_pad((string) random_int(0, (10 ** self::LENGTH) - 1), self::LENGTH, '0', STR_PAD_LEFT);

        Cache::put(
            $this->key($user, 'code'),
            ['hash' => Hash::make($code), 'attempts' => 0, 'expires_at' => time() + $ttl],
            $ttl,
        );

        $user->notify(new ReauthCodeNotification($code, intdiv($ttl, 60)));

        return $ttl;
    }

    /**
     * Spends the code, or throws ACCOUNT_001 when it is wrong, expired or
     * already used. The lock keeps two requests from spending one code.
     */
    public function consume(User $user, string $code): void
    {
        try {
            $valid = Cache::lock($this->key($user, 'lock'), 10)->block(5, fn (): bool => $this->check($user, $code));
        } catch (LockTimeoutException) {
            $valid = false;
        }

        if (! $valid) {
            throw new DomainException(ErrorCode::ACCOUNT_REAUTH_INVALID);
        }
    }

    private function check(User $user, string $code): bool
    {
        $key = $this->key($user, 'code');
        $stored = Cache::get($key);

        if (! is_array($stored) || ! is_string($stored['hash'] ?? null)) {
            return false;
        }

        if (Hash::check($code, $stored['hash'])) {
            Cache::forget($key);

            return true;
        }

        $attempts = (int) ($stored['attempts'] ?? 0) + 1;
        $secondsLeft = (int) ($stored['expires_at'] ?? 0) - time();

        if ($attempts >= (int) config('qbazaar.account.reauth_code_max_attempts') || $secondsLeft <= 0) {
            Cache::forget($key);
        } else {
            Cache::put($key, [...$stored, 'attempts' => $attempts], $secondsLeft);
        }

        return false;
    }

    private function key(User $user, string $suffix): string
    {
        return "account:reauth:{$user->id}:{$suffix}";
    }
}
