<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Actions\Admin\LoginStaffAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Session authentication for the custom /manage panel. Uses the stateful web
 * guard (cookie session) — distinct from the Sanctum token guard the public
 * API uses. Only active staff are allowed past the login gate.
 */
class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function login(Request $request, LoginStaffAction $loginStaff): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $loginStaff->execute($request, $credentials['email'], $credentials['password'], $request->boolean('remember'));

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
