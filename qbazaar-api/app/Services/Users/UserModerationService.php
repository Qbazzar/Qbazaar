<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Admin\StaffHierarchy;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Support\Facades\DB;

/**
 * Staff decisions on a user's account, shared by the users screen and the
 * reports queue so both enforce the staff hierarchy the same way.
 */
class UserModerationService
{
    public function __construct(
        private readonly StaffHierarchy $hierarchy,
        private readonly RefreshTokenService $refreshTokens,
    ) {}

    /**
     * Suspends the user and signs them out everywhere. Flipping the status
     * alone is not enough: live refresh tokens would keep minting access
     * tokens, so sessions are revoked even when the user was already suspended.
     */
    public function suspend(User $actor, User $user): void
    {
        $this->hierarchy->ensureCanManage($actor, $user);

        DB::transaction(function () use ($user): void {
            $user->forceFill(['status' => UserStatus::SUSPENDED])->save();

            $this->refreshTokens->revokeAllSessions($user);
            $user->deviceTokens()->delete();
        });
    }

    public function activate(User $actor, User $user): void
    {
        $this->hierarchy->ensureCanManage($actor, $user);

        $user->forceFill(['status' => UserStatus::ACTIVE])->save();
    }
}
