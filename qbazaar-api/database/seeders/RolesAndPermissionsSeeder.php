<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the staff RBAC layer used by the Filament admin panel.
 *
 * Two-step bootstrap:
 *   1. Permissions + Roles get created idempotently. We use firstOrCreate so
 *      re-running the seeder in dev does not duplicate rows or trip on the
 *      (name, guard_name) unique key.
 *   2. The first matching admin user is granted `super_admin`. If no admin
 *      user exists yet we create the canonical admin account so a fresh
 *      `migrate:fresh --seed` produces a working /admin login out of the box.
 *
 * Why permissions live here (and not in a dedicated config file)?
 *   - Spatie Permission expects rows in DB; centralising the canonical list
 *     in one seeder means we have one source of truth and one place to
 *     extend when a new admin feature ships.
 *
 * The admin guard is `web` because Filament authenticates against the same
 * session/cookie stack as a regular Laravel web app. Sanctum tokens are for
 * the public mobile/web API only.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Canonical permission catalogue. Grouped only for readability; the
     * underlying string IDs are what Spatie persists.
     *
     * @var list<string>
     */
    private const array PERMISSIONS = [
        // Users
        'users.view',
        'users.update',
        'users.ban',
        'users.delete',
        'users.impersonate',

        // Ads
        'ads.view',
        'ads.update',
        'ads.delete',
        'ads.approve',
        'ads.reject',
        'ads.feature',

        // Taxonomy
        'categories.manage',
        'locations.manage',

        // Reports / moderation queue
        'reports.view',
        'reports.action',

        // Broadcast notifications
        'notifications.broadcast',

        // Moderation rule DB editor
        'moderation-rules.manage',

        // CMS (Sprint 12 pre-wire so admins are ready when those resources land)
        'pages.manage',
        'articles.manage',
    ];

    /**
     * Moderator role — read everything + ad lifecycle moderation + the safe
     * subset of user actions (ban / unban). Cannot delete users, cannot
     * impersonate, cannot touch CMS.
     *
     * @var list<string>
     */
    private const array MODERATOR_PERMISSIONS = [
        'users.view',
        'users.ban',
        'ads.view',
        'ads.update',
        'ads.approve',
        'ads.reject',
        'ads.feature',
        'categories.manage',
        'locations.manage',
        'reports.view',
        'reports.action',
        'notifications.broadcast',
        'moderation-rules.manage',
    ];

    /**
     * Support role — read-only with a single write surface (broadcast).
     * Designed for first-line customer support; cannot moderate ads or ban.
     *
     * @var list<string>
     */
    private const array SUPPORT_PERMISSIONS = [
        'users.view',
        'ads.view',
        'reports.view',
        'notifications.broadcast',
    ];

    public function run(): void
    {
        // Spatie caches the resolved permission table aggressively. Clearing
        // first guarantees a re-seed in CI sees the latest entries.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $superAdmin = Role::findOrCreate('super_admin', 'web');
        $superAdmin->syncPermissions(self::PERMISSIONS);

        $moderator = Role::findOrCreate('moderator', 'web');
        $moderator->syncPermissions(self::MODERATOR_PERMISSIONS);

        $support = Role::findOrCreate('support', 'web');
        $support->syncPermissions(self::SUPPORT_PERMISSIONS);

        $this->ensureSuperAdminUser();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Pick or provision the canonical admin user, then attach super_admin.
     *
     * We look up by the well-known seeded email first so re-seeds remain
     * idempotent. If the row is missing (fresh install), we create it with
     * a deterministic dev password — operators are expected to rotate it
     * immediately in production.
     */
    private function ensureSuperAdminUser(): void
    {
        $email = 'admin@qbazaar.qa';

        /** @var User $admin */
        $admin = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'full_name' => 'QBazaar Admin',
                'phone' => '+97455000000',
                'password' => Hash::make('password'),
                'email_verified' => true,
                'phone_verified' => true,
            ],
        );

        if (! $admin->hasRole('super_admin')) {
            $admin->assignRole('super_admin');
        }
    }
}
