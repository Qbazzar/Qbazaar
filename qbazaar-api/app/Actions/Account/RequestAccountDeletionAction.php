<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Enums\UserStatus;
use App\Jobs\DeleteAccountJob;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Schedules a user's account for hard deletion after a grace period
 * (`qbazaar.account.deletion_grace_period_days`, default 30).
 *
 * State transitions:
 *   ACTIVE → PENDING_DELETION
 *   sessions: all burnt (refresh + PAT) so the user is signed out everywhere.
 *
 * The cancel path is implicit — if the user signs back in during the grace
 * window the LoginController flips them back to ACTIVE and the queued
 * DeleteAccountJob re-checks the status before doing anything destructive.
 */
class RequestAccountDeletionAction
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokens,
    ) {}

    public function execute(User $user, ?string $reason = null): CarbonImmutable
    {
        $graceDays = (int) config('qbazaar.account.deletion_grace_period_days', 30);
        $scheduledAt = Carbon::now()->addDays($graceDays);

        DB::transaction(function () use ($user, $reason): void {
            $user->forceFill([
                'status' => UserStatus::PENDING_DELETION,
                'deletion_requested_at' => Carbon::now(),
            ])->save();

            if ($reason !== null && $reason !== '') {
                activity('user')
                    ->performedOn($user)
                    ->causedBy($user)
                    ->event('account_deletion_requested_reason')
                    ->withProperties(['reason' => $reason])
                    ->log('User supplied a reason for deletion');
            }

            $this->refreshTokens->burnAllForUser($user);
            $user->tokens()->delete();

            // Pushes must stop when sessions are burned — drop FCM tokens too.
            $user->deviceTokens()->delete();
        });

        // Dispatched after the commit so the job never runs against a request
        // that rolled back. If this dispatch is lost, the daily
        // SweepDueAccountDeletionsJob still picks the account up.
        DeleteAccountJob::dispatch($user->id)->delay($scheduledAt);

        return CarbonImmutable::instance($scheduledAt);
    }
}
