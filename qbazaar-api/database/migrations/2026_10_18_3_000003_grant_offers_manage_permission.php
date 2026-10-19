<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Production deploys do not run the seeders, so the permission to cancel an
 * offer from the panel is created here and granted to the roles that already
 * moderate (super_admin and moderator).
 */
return new class extends Migration
{
    private const string PERMISSION = 'offers.manage';

    private const array ROLES = ['super_admin', 'moderator'];

    public function up(): void
    {
        $permission = Permission::findOrCreate(self::PERMISSION, 'web');

        Role::query()
            ->whereIn('name', self::ROLES)
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', self::PERMISSION)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
