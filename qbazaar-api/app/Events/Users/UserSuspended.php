<?php

declare(strict_types=1);

namespace App\Events\Users;

use App\Models\ModerationSanction;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Staff suspended an account; `sanction` carries the reason. Fires only once
 * the suspension is committed (UserStatusChanged fires beside it for the
 * listing-visibility side effects).
 */
class UserSuspended implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly ModerationSanction $sanction,
    ) {}
}
