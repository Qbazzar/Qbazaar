<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Filament\Admin\Resources\UserResource\Pages\EditUser;
use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
    actingAs($this->admin);
});

it('edits a user through the EditUser form', function (): void {
    // NOTE: EditUser / ViewUser authorization is governed by AccountPolicy,
    // which is owner-only (see final report — flagged bug). We therefore edit
    // the acting admin's OWN record, the only path the policy currently allows.
    $admin = $this->admin;

    Livewire::test(EditUser::class, ['record' => $admin->getKey()])
        ->fillForm([
            'full_name' => 'Updated Name',
            'email' => $admin->email,
            'phone' => $admin->phone,
            'language' => $admin->language->value,
            'account_type' => $admin->account_type->value,
            'status' => $admin->status->value,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($admin->refresh()->full_name)->toBe('Updated Name');
});

it('lets a super_admin edit another user (Gate::before override)', function (): void {
    $other = User::factory()->create();

    // AccountPolicy is owner-only, but the Gate::before super_admin override in
    // AuthServiceProvider lets staff manage any account from UserResource.
    Livewire::test(EditUser::class, ['record' => $other->getKey()])
        ->fillForm([
            'full_name' => 'Edited By Admin',
            'email' => $other->email,
            'phone' => $other->phone,
            'language' => $other->language->value,
            'account_type' => $other->account_type->value,
            'status' => $other->status->value,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($other->refresh()->full_name)->toBe('Edited By Admin');
});

it('forbids a non-super_admin from editing another user', function (): void {
    $support = User::factory()->create();
    $support->assignRole('support');
    actingAs($support);

    // Without the super_admin override, AccountPolicy::update() is owner-only,
    // so support staff cannot open another account's edit page.
    $other = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $other->getKey()])
        ->assertForbidden();
});

it('queues a password reset link via the password broker', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callTableAction('reset_password', $user);

    Notification::assertSentTo($user, PasswordResetNotification::class);
});

it('bans an active user and unbans a suspended one (toggle)', function (): void {
    $active = User::factory()->create(['status' => UserStatus::ACTIVE->value]);

    Livewire::test(ListUsers::class)
        ->callTableAction('ban', $active);

    expect($active->refresh()->status)->toBe(UserStatus::SUSPENDED);

    Livewire::test(ListUsers::class)
        ->callTableAction('ban', $active);

    expect($active->refresh()->status)->toBe(UserStatus::ACTIVE);
});

it('syncs roles via the super_admin-gated roles field', function (): void {
    $admin = $this->admin;
    $moderatorRole = Role::findByName('moderator', 'web');

    // Edit own record (owner-only policy). The roles section is visible only
    // to a super_admin, so this also proves the gate is open for one.
    Livewire::test(EditUser::class, ['record' => $admin->getKey()])
        ->assertFormFieldExists('roles')
        ->fillForm([
            'full_name' => $admin->full_name,
            'email' => $admin->email,
            'phone' => $admin->phone,
            'language' => $admin->language->value,
            'account_type' => $admin->account_type->value,
            'status' => $admin->status->value,
            'roles' => [$moderatorRole->getKey()],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($admin->refresh()->hasRole('moderator'))->toBeTrue();
});

it('hides the roles field from a non-super_admin staff member', function (): void {
    $support = User::factory()->create();
    $support->assignRole('support');
    actingAs($support);

    // Support edits their own record (owner-only policy); the roles section is
    // gated behind a super_admin visibility closure, so it must be absent.
    Livewire::test(EditUser::class, ['record' => $support->getKey()])
        ->assertFormFieldDoesNotExist('roles');
});

it('bulk-bans selected users', function (): void {
    $users = User::factory()->count(3)->create(['status' => UserStatus::ACTIVE->value]);

    Livewire::test(ListUsers::class)
        ->callTableBulkAction('bulk_ban', $users);

    $users->each(fn (User $u) => expect($u->refresh()->status)->toBe(UserStatus::SUSPENDED));
});

it('bulk-releases (unbans) selected users', function (): void {
    $users = User::factory()->count(3)->create(['status' => UserStatus::SUSPENDED->value]);

    Livewire::test(ListUsers::class)
        ->callTableBulkAction('bulk_suspend_release', $users);

    $users->each(fn (User $u) => expect($u->refresh()->status)->toBe(UserStatus::ACTIVE));
});

it('soft-deletes a user via the delete action', function (): void {
    $user = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callTableAction('delete', $user);

    expect(User::query()->whereKey($user->getKey())->exists())->toBeFalse();
});
