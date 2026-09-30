<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Support\Facades\DB;

/**
 * Suspends a user and signs them out everywhere. Flipping the status alone is
 * not enough: live refresh tokens would keep minting access tokens.
 */
class SuspendUserAction
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokens,
    ) {}

    public function execute(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->forceFill(['status' => UserStatus::SUSPENDED])->save();

            $this->refreshTokens->revokeAllSessions($user);
            $user->deviceTokens()->delete();
        });
    }
}
