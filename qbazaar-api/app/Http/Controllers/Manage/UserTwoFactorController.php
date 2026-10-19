<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\StaffHierarchy;
use App\Services\Admin\TwoFactor\StaffTwoFactorService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/**
 * Clears a junior colleague's two-factor sign-in after a lost phone, so they
 * can enrol again on their next sign-in. Only downwards in the staff
 * hierarchy; a super admin locked out of their own account uses
 * `php artisan admin:two-factor-reset`.
 */
class UserTwoFactorController extends Controller
{
    public function destroy(
        #[CurrentUser]
        User $actor,
        User $user,
        StaffHierarchy $hierarchy,
        StaffTwoFactorService $twoFactor,
    ): RedirectResponse {
        $hierarchy->ensureCanManage($actor, $user);

        $twoFactor->disable($actor, $user);

        return back()->with('status', __('admin.two_factor.flash.reset', ['name' => $user->full_name ?: $user->email]));
    }
}
