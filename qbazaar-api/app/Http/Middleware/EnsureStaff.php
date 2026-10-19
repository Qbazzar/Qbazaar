<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Admin\TwoFactor\StaffTwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the /admin panel to active staff. Per-screen access is enforced on
 * each route with Spatie's `permission:` middleware. Staff whose role must
 * use two-factor sign-in only reach their own account pages until enrolled.
 */
class EnsureStaff
{
    public function __construct(private readonly StaffTwoFactorService $twoFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('admin.login');
        }

        if (! $user->isStaff()) {
            abort(403);
        }

        // A suspension must end sessions that were opened before it.
        if (! $user->status->canLogin()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->withErrors(['email' => __('admin.auth.account_inactive')]);
        }

        if ($user->must_change_password && ! $request->routeIs('admin.profile.*')) {
            return redirect()->route('admin.profile.edit')->with('error', __('admin.auth.password_change_required'));
        }

        if ($this->mustEnrolTwoFactor($request, $user)) {
            return redirect()->route('admin.profile.two-factor.show')->with('error', __('admin.two_factor.enrolment_required'));
        }

        return $next($request);
    }

    private function mustEnrolTwoFactor(Request $request, User $user): bool
    {
        return ! $request->routeIs('admin.profile.*')
            && $this->twoFactor->isRequired($user)
            && ! $this->twoFactor->isEnabled($user);
    }
}
