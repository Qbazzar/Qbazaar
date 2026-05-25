<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('redirects guests away from the admin panel', function (): void {
    // Filament's auth middleware bounces unauthenticated requests to the
    // panel login page — a 200 here would mean our guard isn't engaged.
    get('/admin')->assertRedirect('/admin/login');
});

it('forbids regular users from reaching the admin panel', function (): void {
    $regular = User::factory()->create();

    actingAs($regular)
        ->get('/admin')
        ->assertForbidden();
});

it('lets a super_admin user into the admin panel', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    actingAs($admin)
        ->get('/admin')
        ->assertSuccessful();
});
