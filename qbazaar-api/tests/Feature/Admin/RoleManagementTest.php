<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\RoleResource;
use App\Filament\Admin\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Admin\Resources\RoleResource\Pages\EditRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
    actingAs($this->admin);
});

it('lets a super_admin create a role with permissions', function (): void {
    $permissionIds = Permission::query()
        ->whereIn('name', ['users.view', 'ads.view'])
        ->pluck('id')
        ->all();

    Livewire::test(CreateRole::class)
        ->fillForm([
            'name' => 'auditor',
            'guard_name' => 'web',
            'permissions' => $permissionIds,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $role = Role::findByName('auditor', 'web');
    expect($role->hasPermissionTo('users.view'))->toBeTrue();
    expect($role->hasPermissionTo('ads.view'))->toBeTrue();
});

it('attaches permissions when editing an existing role', function (): void {
    $role = Role::findOrCreate('viewer', 'web');
    $permissionId = Permission::query()->where('name', 'reports.view')->value('id');

    Livewire::test(EditRole::class, ['record' => $role->getKey()])
        ->fillForm([
            'name' => 'viewer',
            'permissions' => [$permissionId],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($role->refresh()->hasPermissionTo('reports.view'))->toBeTrue();
});

it('forbids a non-super_admin from accessing the role resource', function (): void {
    foreach (['moderator', 'support'] as $roleName) {
        $staff = User::factory()->create();
        $staff->assignRole($roleName);
        actingAs($staff);

        expect(RoleResource::canAccess())->toBeFalse();
    }
});
