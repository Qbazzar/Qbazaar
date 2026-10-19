<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\TwoFactor\StaffTwoFactorService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Lets a signed-in staff member manage their own admin account — display name,
 * email, and password — without needing another admin to edit them.
 */
class ProfileController extends Controller
{
    public function edit(Request $request, StaffTwoFactorService $twoFactor): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('admin.profile.edit', [
            'user' => $user->loadMissing(['roles', 'avatarMedia']),
            'twoFactorEnabled' => $twoFactor->isEnabled($user),
            'twoFactorRequired' => $twoFactor->isRequired($user),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => [
                Rule::requiredIf($user->must_change_password),
                'nullable',
                'confirmed',
                'min:' . config('qbazaar.auth.password_min_length'),
                function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                    if (is_string($value) && $user->passwordMatches($value)) {
                        $fail(__('admin.auth.password_reused'));
                    }
                },
            ],
        ]);

        $user->full_name = $data['full_name'];
        $user->email = $data['email'];

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
            $user->must_change_password = false;
        }

        $user->save();

        return back()->with('status', __('admin.flash.profile_updated'));
    }
}
