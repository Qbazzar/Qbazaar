<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Support\Facades\DB;

/**
 * Runs when someone proves they own an account's email with an email code or
 * a Google / Apple token. While the email was unverified, whoever registered
 * it may not have owned it (pre-registering a victim's address), so that
 * person's password, sessions and trusted devices are dropped before the
 * proven owner gets in.
 */
class ConfirmEmailOwnershipAction
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokens,
    ) {}

    public function execute(User $user): void
    {
        if ($user->email_verified) {
            return;
        }

        DB::transaction(function () use ($user): void {
            $user->forceFill(['email_verified' => true, 'password' => null])->save();

            $this->refreshTokens->revokeAllSessions($user);

            TrustedDevice::query()->where('user_id', $user->id)->delete();
        });
    }
}
