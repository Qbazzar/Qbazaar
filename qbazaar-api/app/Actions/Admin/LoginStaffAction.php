<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Signs a staff member into the admin panel's session guard. Non-staff and
 * inactive accounts get the same generic error as a wrong password so the
 * form cannot be used to probe which emails belong to staff.
 */
class LoginStaffAction
{
    /**
     * @throws ValidationException
     */
    public function execute(Request $request, string $email, string $password, bool $remember): void
    {
        $throttleKey = $this->throttleKey($email, (string) $request->ip());

        $this->ensureNotLockedOut($request, $throttleKey);

        $guard = Auth::guard('web');
        if (! $guard instanceof SessionGuard) {
            throw new LogicException('Staff login requires the session-based web guard.');
        }

        $authenticated = $guard->attemptWhen(
            ['email' => Str::lower($email), 'password' => $password],
            fn (User $user): bool => $user->isStaff() && $user->status->canLogin(),
            $remember,
        );

        if (! $authenticated) {
            RateLimiter::hit($throttleKey, (int) config('qbazaar.admin.login_lockout_seconds'));

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
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
