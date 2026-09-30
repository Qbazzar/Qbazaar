<?php

declare(strict_types=1);

namespace App\Events\Users;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a user's account status changes, whichever surface made the
 * change (admin suspension, report ban, self-deactivation, reactivation).
 */
class UserStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly UserStatus $previousStatus,
    ) {}

    public function becameActive(): bool
    {
        return $this->previousStatus !== UserStatus::ACTIVE
            && $this->user->status === UserStatus::ACTIVE;
    }

    public function becameInactive(): bool
    {
        return $this->previousStatus === UserStatus::ACTIVE
            && $this->user->status !== UserStatus::ACTIVE;
    }
}
