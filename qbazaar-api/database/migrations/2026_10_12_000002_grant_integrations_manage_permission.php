<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Production deploys do not run the seeders, so the permission guarding the
 * credential tabs is created here and granted to super_admin only.
 */
return new class extends Migration
{
    private const string PERMISSION = 'integrations.manage';

    public function up(): void
    {
        $permission = Permission::findOrCreate(self::PERMISSION, 'web');

        Role::query()->where('name', 'super_admin')->where('guard_name', 'web')->first()?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', self::PERMISSION)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
