<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Support\Facades\DB;

/**
 * Marks a user as DEACTIVATED and signs them out everywhere.
 *
 * Why a single-purpose action?
 *  - Two state transitions need to ship atomically: flip status + burn
 *    sessions. Splitting that across a controller would leak the contract.
 *  - The Observer already turns the `status` change into an
 *    `activity_log.status_changed` row — we just need the action to fire
 *    that change in a transaction so the audit row only lands if both the
 *    DB write and the token burns succeed.
 *
 * The user can self-reactivate by logging back in within 30 days; login
 * already raises AUTH_002 for non-ACTIVE accounts, so we add a special path
 * there (handled in LoginController + LoginUserAction).
 *
 * `$reason` is recorded as a properties row on the next activity_log entry
 * via `activity()->withProperties()` so we don't need a new column on the
 * users table.
 */
class DeactivateAccountAction
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokens,
    ) {}

    public function execute(User $user, ?string $reason = null): void
    {
        DB::transaction(function () use ($user, $reason): void {
            $user->forceFill(['status' => UserStatus::DEACTIVATED])->save();

            if ($reason !== null && $reason !== '') {
                activity('user')
                    ->performedOn($user)
                    ->causedBy($user)
                    ->event('account_deactivated')
                    ->withProperties(['reason' => $reason])
                    ->log('User deactivated their account');
            }

            $this->refreshTokens->burnAllForUser($user);

            // Burn EVERY Sanctum personal access token — unlike password
            // change we don't keep the current device alive: the user
            // explicitly asked to be logged out.
            $user->tokens()->delete();
        });
    }
}
