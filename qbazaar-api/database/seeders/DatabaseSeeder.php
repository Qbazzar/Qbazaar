<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Reference data (Sprint 3) — public taxonomy + Qatar locations.
        // Seeded first so factories in later seeders/tests can reference categories.
        $this->call([
            CategorySeeder::class,
            LocationSeeder::class,
            // Sprint 11 — admin RBAC + DB-backed moderation rules.
            // Roles must exist before the admin user is granted super_admin,
            // and moderation rules must be present so the publish hot path
            // hits a populated table instead of a slow config-fallback.
            RolesAndPermissionsSeeder::class,
            ModerationRulesSeeder::class,
        ]);

        User::factory()->create([
            'full_name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+97455000001',
        ]);
    }
}
