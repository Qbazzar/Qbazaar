<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates a (blocker → blocked) pivot row, with the two business rules:
 *  - USER_003 — a user cannot block themselves.
 *  - USER_002 — admins cannot be blocked (admin role detection via Spatie
 *    permissions when present; placeholder check below until the roles
 *    seeder ships in Sprint 11).
 *
 * Idempotent: duplicate POSTs return the existing pivot row without raising
 * a constraint error. We use a raw insertOrIgnore so the composite primary
 * key handles the race naturally.
 */
class BlockUserAction
{
    public function execute(User $blocker, User $blocked): void
    {
        if ($blocker->id === $blocked->id) {
            throw new DomainException(ErrorCode::USER_BLOCK_SELF_FORBIDDEN);
        }

        if ($this->isAdmin($blocked)) {
            throw new DomainException(ErrorCode::USER_BLOCK_ADMIN_FORBIDDEN);
        }

        DB::table('user_blocks')->insertOrIgnore([
            'blocker_id' => $blocker->id,
            'blocked_id' => $blocked->id,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Light-touch admin detection.
     *
     * Sprint 11 ships the full RBAC seed (admin / moderator / merchant);
     * until then we ask Spatie\Permission whether the role exists on the
     * user. The check is safe to run before any role is assigned — it
     * simply returns false.
     */
    private function isAdmin(User $user): bool
    {
        /** @var bool $hasAdmin */
        $hasAdmin = $user->hasRole('super_admin');

        return $hasAdmin;
    }
}
