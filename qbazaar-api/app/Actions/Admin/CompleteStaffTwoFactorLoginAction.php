<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;
use App\Services\Admin\TwoFactor\PendingTwoFactorLogin;
use App\Services\Admin\TwoFactor\StaffTwoFactorService;
use App\Services\Admin\TwoFactor\TrustedBrowser;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Finishes a sign-in that is waiting for its authenticator or recovery code.
 * Wrong codes count per account (not per IP), so guessing from many
 * addresses locks the prompt just as fast.
 */
class CompleteStaffTwoFactorLoginAction
{
    public function __construct(
        private readonly StaffTwoFactorService $twoFactor,
        private readonly TrustedBrowser $trustedBrowser,
        private readonly PendingTwoFactorLogin $pending,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(Request $request, User $user, string $code, bool $recovery, bool $trustBrowser): void
    {
        $field = $recovery ? 'recovery_code' : 'code';
        $throttleKey = 'admin-2fa:' . $user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, (int) config('qbazaar.admin.two_factor.max_attempts'))) {
            event(new Lockout($request));

            throw ValidationException::withMessages([
                $field => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        if (! $this->twoFactor->verifyLogin($user, $code, $recovery)) {
            RateLimiter::hit($throttleKey, (int) config('qbazaar.admin.two_factor.lockout_seconds'));

            throw ValidationException::withMessages([$field => __('admin.two_factor.errors.invalid_code')]);
        }

        RateLimiter::clear($throttleKey);
        $remember = $this->pending->wantsRemember($request);
        $this->pending->clear($request);

        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        $credential = $this->twoFactor->credentialFor($user);
        if ($trustBrowser && $credential !== null) {
            $this->trustedBrowser->remember($user, $credential);
        }
    }
}
