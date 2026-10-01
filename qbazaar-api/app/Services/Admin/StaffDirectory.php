<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Finds staff by permission. The lookup starts from Spatie's pivot tables,
 * which hold only staff, so it never scans the users table: a whereHas on
 * users would test every customer row against the role subqueries.
 */
class StaffDirectory
{
    /**
     * Users holding the permission through a role or directly.
     *
     * @return list<string>
     */
    public function idsWithPermission(string $permission): array
    {
        $permissionIds = Permission::query()->where('name', $permission)->pluck('id')->all();

        if ($permissionIds === []) {
            return [];
        }

        /** @var array<string, string> $tables */
        $tables = config('permission.table_names');
        $morphKey = (string) config('permission.column_names.model_morph_key', 'model_id');
        $rolePivotKey = (string) (config('permission.column_names.role_pivot_key') ?? 'role_id');
        $permissionPivotKey = (string) (config('permission.column_names.permission_pivot_key') ?? 'permission_id');
        $userMorphClass = (new User)->getMorphClass();

        $roleIds = DB::table($tables['role_has_permissions'])
            ->whereIn($permissionPivotKey, $permissionIds)
            ->pluck($rolePivotKey)
            ->all();

        $viaRoles = $roleIds === [] ? [] : DB::table($tables['model_has_roles'])
            ->where('model_type', $userMorphClass)
            ->whereIn($rolePivotKey, $roleIds)
            ->pluck($morphKey)
            ->all();

        $direct = DB::table($tables['model_has_permissions'])
            ->where('model_type', $userMorphClass)
            ->whereIn($permissionPivotKey, $permissionIds)
            ->pluck($morphKey)
            ->all();

        return array_values(array_unique(array_map('strval', [...$viaRoles, ...$direct])));
    }
}
