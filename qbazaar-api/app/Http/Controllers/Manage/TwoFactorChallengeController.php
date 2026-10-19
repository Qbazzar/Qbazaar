<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Actions\Admin\CompleteStaffTwoFactorLoginAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\TwoFactor\TwoFactorChallengeRequest;
use App\Services\Admin\TwoFactor\PendingTwoFactorLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The code prompt between the password and the panel. Without a fresh
 * pending sign-in in the session it sends the visitor back to the password.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(private readonly PendingTwoFactorLogin $pending) {}

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->pending->user($request) === null) {
            return $this->expired($request);
        }

        return view('admin.two-factor.challenge', [
            'trustedDays' => (int) config('qbazaar.admin.two_factor.trusted_browser_days'),
        ]);
    }

    public function store(TwoFactorChallengeRequest $request, CompleteStaffTwoFactorLoginAction $complete): RedirectResponse
    {
        $user = $this->pending->user($request);

        if ($user === null) {
            return $this->expired($request);
        }

        $complete->execute($request, $user, $request->submittedCode(), $request->usesRecoveryCode(), $request->trustBrowser());

        return redirect()->intended(route('admin.dashboard'));
    }

    private function expired(Request $request): RedirectResponse
    {
        $this->pending->clear($request);

        return redirect()->route('admin.login')->withErrors(['email' => __('admin.two_factor.errors.challenge_expired')]);
    }
}
