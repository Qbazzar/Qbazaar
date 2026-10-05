<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Reference data every environment needs, in dependency order: the
     * taxonomy first so later seeders can point at it, roles before the admin
     * account they grant, and moderation rules so the publish path never
     * falls back to config. Demo users, ads and deals are generated
     * separately by `php artisan qbazaar:demo`.
     *
     * @var list<class-string<Seeder>>
     */
    public const array REFERENCE_SEEDERS = [
        CategorySeeder::class,
        LocationSeeder::class,
        RolesAndPermissionsSeeder::class,
        ModerationRulesSeeder::class,
        PageSeeder::class,
        HelpSeeder::class,
    ];

    public function run(): void
    {
        $this->call(self::REFERENCE_SEEDERS);

        User::factory()->create([
            'full_name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+97455000001',
        ]);
    }
}
