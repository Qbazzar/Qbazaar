<?php

declare(strict_types=1);

namespace App\Services\Admin\TwoFactor;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;

/**
 * Setting up two-factor sign-in starts with the password. For a short
 * window after it, the same session sees the new key and confirms it
 * without typing the password again; once the window closes, the setup has
 * to start over with the password and a fresh key, so an unattended
 * session can neither read a key nor turn two-factor on.
 *
 * The time is kept under the session key Laravel's password.confirm
 * middleware uses, so both agree on when the password was last confirmed.
 */
class EnrolmentPasswordConfirmation
{
    private const string SESSION_KEY = 'auth.password_confirmed_at';

    public function isRecent(Session $session): bool
    {
        $confirmedAt = (int) $session->get(self::SESSION_KEY, 0);
        $window = (int) config('qbazaar.admin.two_factor.password_confirm_minutes') * 60;

        return Carbon::now()->getTimestamp() - $confirmedAt < $window;
    }

    public function confirm(Session $session): void
    {
        $session->put(self::SESSION_KEY, Carbon::now()->getTimestamp());
    }
}
