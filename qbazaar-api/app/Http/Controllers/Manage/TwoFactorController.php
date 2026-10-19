<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\TwoFactor\ConfirmPasswordRequest;
use App\Http\Requests\Manage\TwoFactor\ConfirmTwoFactorRequest;
use App\Models\User;
use App\Services\Admin\TwoFactor\StaffTwoFactorService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A signed-in staff member's own two-factor sign-in: enrol, confirm,
 * replace the recovery codes, or turn it off. Recovery codes are flashed to
 * the next page view only and never stored in plain text.
 */
class TwoFactorController extends Controller
{
    private const string RECOVERY_CODES_FLASH = 'two_factor_recovery_codes';

    public function __construct(private readonly StaffTwoFactorService $twoFactor) {}

    public function show(Request $request, #[CurrentUser] User $user): View
    {
        $credential = $this->twoFactor->credentialFor($user);
        $pending = $credential !== null && ! $credential->isConfirmed();

        return view('admin.profile.two-factor', [
            'user' => $user,
            'credential' => $credential,
            'enabled' => $credential?->isConfirmed() ?? false,
            'required' => $this->twoFactor->isRequired($user),
            'secret' => $pending ? $credential->secret : null,
            // Grouped in fours so the key can be read out and typed without losing the place.
            'secretGroups' => $pending ? trim(chunk_split($credential->secret, 4, ' ')) : null,
            'provisioningUri' => $pending ? $this->twoFactor->provisioningUri($user, $credential) : null,
            'recoveryCodes' => $request->session()->get(self::RECOVERY_CODES_FLASH),
            'trustedDays' => (int) config('qbazaar.admin.two_factor.trusted_browser_days'),
        ]);
    }

    public function store(#[CurrentUser] User $user): RedirectResponse
    {
        $this->twoFactor->begin($user);

        return redirect()->route('admin.profile.two-factor.show')->with('status', __('admin.two_factor.flash.started'));
    }

    public function confirm(ConfirmTwoFactorRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $codes = $this->twoFactor->confirm($user, $request->code());

        return redirect()->route('admin.profile.two-factor.show')
            ->with('status', __('admin.two_factor.flash.enabled'))
            ->with(self::RECOVERY_CODES_FLASH, $codes);
    }

    public function regenerateRecoveryCodes(ConfirmPasswordRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $codes = $this->twoFactor->regenerateRecoveryCodes($user);

        return redirect()->route('admin.profile.two-factor.show')
            ->with('status', __('admin.two_factor.flash.recovery_regenerated'))
            ->with(self::RECOVERY_CODES_FLASH, $codes);
    }

    public function destroy(ConfirmPasswordRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $this->twoFactor->disable($user, $user);

        return redirect()->route('admin.profile.two-factor.show')->with('status', __('admin.two_factor.flash.disabled'));
    }
}
