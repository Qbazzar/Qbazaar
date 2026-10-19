<?php

declare(strict_types=1);

namespace App\Services\Admin\TwoFactor;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The half-finished sign-in between the password and the code: kept in the
 * session only, never as an authenticated user, so nothing that trusts the
 * web guard (the panel, Horizon, Pulse) sees the account before the code.
 */
class PendingTwoFactorLogin
{
    private const string KEY = 'admin.two_factor.pending';

    public function start(Request $request, User $user, bool $remember): void
    {
        $request->session()->put(self::KEY, [
            'user_id' => $user->id,
            'remember' => $remember,
            'expires_at' => Carbon::now()->addMinutes((int) config('qbazaar.admin.two_factor.challenge_ttl_minutes'))->getTimestamp(),
        ]);
    }

    /**
     * The staff member waiting for their code, while the prompt is fresh and
     * the account may still sign in.
     */
    public function user(Request $request): ?User
    {
        $pending = $request->session()->get(self::KEY);

        if (! is_array($pending) || ($pending['expires_at'] ?? 0) <= Carbon::now()->getTimestamp()) {
            return null;
        }

        $userId = $pending['user_id'] ?? null;
        if (! is_string($userId)) {
            return null;
        }

        $user = User::query()->find($userId);

        return $user !== null && $user->isStaff() && $user->status->canLogin() ? $user : null;
    }

    public function wantsRemember(Request $request): bool
    {
        return (bool) ($request->session()->get(self::KEY)['remember'] ?? false);
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::KEY);
    }
}
