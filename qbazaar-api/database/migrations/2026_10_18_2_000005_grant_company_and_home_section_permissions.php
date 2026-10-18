<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Production deploys do not run the seeders, so the permissions behind the
 * companies, business applications and home sections screens are created
 * and granted here, matching RolesAndPermissionsSeeder.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> permission => roles */
    private const array GRANTS = [
        'companies.view' => ['super_admin', 'moderator', 'support'],
        'companies.verify' => ['super_admin', 'moderator'],
        'companies.feature' => ['super_admin', 'moderator'],
        'home-sections.manage' => ['super_admin', 'moderator'],
    ];

    public function up(): void
    {
        foreach (self::GRANTS as $name => $roles) {
            $permission = Permission::findOrCreate($name, 'web');

            Role::query()
                ->where('guard_name', 'web')
                ->whereIn('name', $roles)
                ->get()
                ->each(fn (Role $role) => $role->givePermissionTo($permission));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', array_keys(self::GRANTS))
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
