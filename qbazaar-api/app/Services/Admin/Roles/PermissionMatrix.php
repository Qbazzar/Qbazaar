<?php

declare(strict_types=1);

namespace App\Services\Admin\Roles;

use App\Enums\StaffRole;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Which permission each staff role holds, as edited by a super admin
 * (AD-17.15).
 *
 * super_admin is never edited here: it always holds every permission, so no
 * one can strip their own access or lock the last administrator out. A few
 * permissions stay super_admin-only and cannot be handed to another role,
 * because whoever holds them could raise their own rights.
 */
class PermissionMatrix
{
    public const string GUARD = 'web';

    /** Never granted to a role other than super_admin. */
    public const array RESERVED = ['roles.manage', 'integrations.manage'];

    /** @var Collection<int, Permission>|null */
    private ?Collection $permissions = null;

    public function __construct(private readonly AdminAuditLogger $audit) {}

    /**
     * Roles the matrix may change, with their permissions eager loaded.
     *
     * @return Collection<int, Role>
     */
    public function editableRoles(): Collection
    {
        return Role::query()
            ->where('guard_name', self::GUARD)
            ->where('name', '!=', StaffRole::SUPER_ADMIN->value)
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Permission>
     */
    public function permissions(): Collection
    {
        return $this->permissions ??= Permission::query()->where('guard_name', self::GUARD)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return list<string> permission names any role may receive
     */
    public function assignable(): array
    {
        $names = [];
        foreach ($this->permissions() as $permission) {
            if (! in_array($permission->name, self::RESERVED, true)) {
                $names[] = $permission->name;
            }
        }

        return $names;
    }

    /**
     * Rows of the matrix grouped by area ("users", "ads", …), each cell
     * saying whether the role holds the permission.
     *
     * Labels fall back to the raw name, so a permission added later without
     * copy still shows up.
     *
     * @param Collection<int, Role> $roles
     * @return list<array{label: string, permissions: list<array{name: string, label: string, reserved: bool, granted: array<string, bool>}>}>
     */
    public function rows(Collection $roles): array
    {
        $held = [];
        foreach ($roles as $role) {
            $held[$role->name] = array_flip($role->permissions->pluck('name')->all());
        }

        $groups = [];
        foreach ($this->permissions() as $permission) {
            $group = Str::before($permission->name, '.');
            $groups[$group][] = [
                'name' => $permission->name,
                'label' => $this->label('permissions', $permission->name),
                'reserved' => in_array($permission->name, self::RESERVED, true),
                'granted' => array_map(static fn (array $names): bool => isset($names[$permission->name]), $held),
            ];
        }

        $rows = [];
        foreach ($groups as $key => $permissions) {
            $rows[] = ['label' => $this->label('groups', $key), 'permissions' => $permissions];
        }

        return $rows;
    }

    /**
     * Saves the submitted matrix. A role missing from it ends up with no
     * permissions (every box unticked); reserved permissions are ignored.
     *
     * @param array<string, list<string>> $matrix role name => permission names
     */
    public function update(User $actor, array $matrix): void
    {
        $assignable = $this->assignable();

        DB::transaction(function () use ($actor, $matrix, $assignable): void {
            foreach ($this->editableRoles() as $role) {
                $this->sync($actor, $role, array_values(array_intersect($assignable, $matrix[$role->name] ?? [])));
            }
        });
    }

    /**
     * Lang keys cannot contain dots, so "moderation-rules.manage" reads as
     * "admin.permission_matrix.permissions.moderation_rules_manage".
     */
    private function label(string $kind, string $name): string
    {
        $key = 'admin.permission_matrix.' . $kind . '.' . str_replace(['.', '-'], '_', $name);

        return Lang::has($key) ? (string) __($key) : $name;
    }

    /**
     * @param list<string> $wanted
     */
    private function sync(User $actor, Role $role, array $wanted): void
    {
        $current = $role->permissions->pluck('name')->all();
        $granted = array_values(array_diff($wanted, $current));
        $revoked = array_values(array_diff($current, $wanted));

        if ($granted === [] && $revoked === []) {
            return;
        }

        $role->syncPermissions($wanted);

        $this->audit->record($actor, 'admin.roles.permissions_changed', $role, [
            'role' => $role->name,
            'granted' => $granted,
            'revoked' => $revoked,
        ]);
    }
}
