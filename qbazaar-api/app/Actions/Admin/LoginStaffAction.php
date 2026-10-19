<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\StaffLoginOutcome;
use App\Models\User;
use App\Services\Admin\TwoFactor\PendingTwoFactorLogin;
use App\Services\Admin\TwoFactor\StaffTwoFactorService;
use App\Services\Admin\TwoFactor\TrustedBrowser;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Checks a staff member's password for the admin panel's session guard.
 * Non-staff and inactive accounts get the same generic error as a wrong
 * password so the form cannot be used to probe which emails belong to staff.
 *
 * Staff with two-factor sign-in are not signed in here: the session only
 * remembers who is waiting for their code, unless this browser is trusted.
 */
class LoginStaffAction
{
    public function __construct(
        private readonly StaffTwoFactorService $twoFactor,
        private readonly TrustedBrowser $trustedBrowser,
        private readonly PendingTwoFactorLogin $pending,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(Request $request, string $email, string $password, bool $remember): StaffLoginOutcome
    {
        $throttleKey = $this->throttleKey($email, (string) $request->ip());

        $this->ensureNotLockedOut($request, $throttleKey);

        $guard = Auth::guard('web');
        if (! $guard instanceof SessionGuard) {
            throw new LogicException('Staff login requires the session-based web guard.');
        }

        $credentials = ['email' => Str::lower($email), 'password' => $password];
        $user = $guard->validate($credentials) ? $guard->getLastAttempted() : null;

        if (! $user instanceof User || ! $user->isStaff() || ! $user->status->canLogin()) {
            RateLimiter::hit($throttleKey, (int) config('qbazaar.admin.login_lockout_seconds'));

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($throttleKey);
        $guard->getProvider()->rehashPasswordIfRequired($user, $credentials);
        $request->session()->regenerate();

        $credential = $this->twoFactor->credentialFor($user);
        if ($credential !== null && $credential->isConfirmed() && ! $this->trustedBrowser->recognises($request, $user, $credential)) {
            $this->pending->start($request, $user, $remember);

            return StaffLoginOutcome::TWO_FACTOR_REQUIRED;
        }

        $guard->login($user, $remember);

        return StaffLoginOutcome::AUTHENTICATED;
    }

    private function ensureNotLockedOut(Request $request, string $throttleKey): void
    {
        if (! RateLimiter::tooManyAttempts($throttleKey, (int) config('qbazaar.admin.login_max_attempts'))) {
            return;
        }

        event(new Lockout($request));

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
        ]);
    }

    private function throttleKey(string $email, string $ip): string
    {
        return 'admin-login:' . Str::transliterate(Str::lower($email)) . '|' . $ip;
    }
}
