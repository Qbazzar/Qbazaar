<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\StaffRole;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Account-level actions (suspend, roles, password reset, impersonation) are
 * only allowed downwards: never on yourself and never on a colleague of equal
 * or higher rank, so a compromised or rogue staff account cannot lock out or
 * take over the people above it.
 */
class StaffHierarchy
{
    public function canManage(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        return StaffRole::rankOf($target) < StaffRole::rankOf($actor);
    }

    public function ensureCanManage(User $actor, User $target): void
    {
        if (! $this->canManage($actor, $target)) {
            throw new AccessDeniedHttpException;
        }
    }
}
