<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Admin\StaffHierarchy;

class SyncUserRolesAction
{
    public function __construct(
        private readonly StaffHierarchy $hierarchy,
        private readonly AdminAuditLogger $audit,
    ) {}

    /**
     * @param list<string> $roles
     */
    public function execute(User $actor, User $user, array $roles): void
    {
        $this->hierarchy->ensureCanManage($actor, $user);
        $this->hierarchy->ensureCanGrantRoles($actor, $roles);

        $previousRoles = $user->getRoleNames()->sort()->values()->all();

        $user->syncRoles($roles);

        $this->audit->record($actor, 'admin.users.roles_changed', $user, [
            'old' => $previousRoles,
            'new' => $user->getRoleNames()->sort()->values()->all(),
        ]);
    }
}
