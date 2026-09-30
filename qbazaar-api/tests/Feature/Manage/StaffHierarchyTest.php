<?php

declare(strict_types=1);

use App\Enums\ReportTarget;
use App\Enums\UserStatus;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);

    $this->seed(RolesAndPermissionsSeeder::class);
});

function userWithRole(?string $role = null): User
{
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);

    return $role === null ? $user : $user->assignRole($role);
}

it('refuses account actions on staff of equal or higher rank', function (string $actorRole, string $targetRole): void {
    $target = userWithRole($targetRole);
    actingAs(userWithRole($actorRole));

    $this->post("/admin/users/{$target->id}/suspend")->assertForbidden();

    expect($target->fresh()->status)->toBe(UserStatus::ACTIVE);
})->with([
    'moderator → super_admin' => ['moderator', 'super_admin'],
    'moderator → moderator' => ['moderator', 'moderator'],
    'super_admin → super_admin' => ['super_admin', 'super_admin'],
]);

it('lets staff act on lower-ranked accounts', function (string $actorRole, ?string $targetRole): void {
    $target = userWithRole($targetRole);
    actingAs(userWithRole($actorRole));

    $this->post("/admin/users/{$target->id}/suspend")->assertRedirect();

    expect($target->fresh()->status)->toBe(UserStatus::SUSPENDED);
})->with([
    'moderator → customer' => ['moderator', null],
    'moderator → support' => ['moderator', 'support'],
    'super_admin → moderator' => ['super_admin', 'moderator'],
]);

it('refuses staff acting on their own account', function (string $action): void {
    $admin = userWithRole('super_admin');
    actingAs($admin);

    $this->post("/admin/users/{$admin->id}/{$action}", ['roles' => []])->assertForbidden();

    expect($admin->fresh()->hasRole('super_admin'))->toBeTrue()
        ->and($admin->fresh()->status)->toBe(UserStatus::ACTIVE);
})->with(['suspend', 'activate', 'roles', 'reset-password']);

it('refuses role changes on another super admin', function (): void {
    $peer = userWithRole('super_admin');
    actingAs(userWithRole('super_admin'));

    $this->post("/admin/users/{$peer->id}/roles", ['roles' => []])->assertForbidden();

    expect($peer->fresh()->hasRole('super_admin'))->toBeTrue();
});

it('refuses granting a role above the actor rank', function (): void {
    $customer = userWithRole();
    $moderator = userWithRole('moderator');
    $moderator->givePermissionTo('roles.manage');
    actingAs($moderator);

    $this->post("/admin/users/{$customer->id}/roles", ['roles' => ['super_admin']])->assertForbidden();

    expect($customer->fresh()->hasRole('super_admin'))->toBeFalse();
});

it('lets staff grant roles up to their own rank', function (): void {
    $customer = userWithRole();
    $moderator = userWithRole('moderator');
    $moderator->givePermissionTo('roles.manage');
    actingAs($moderator);

    $this->post("/admin/users/{$customer->id}/roles", ['roles' => ['support']])->assertRedirect();

    expect($customer->fresh()->hasRole('support'))->toBeTrue();
});

it('refuses banning a higher-ranked account through a report', function (): void {
    $superAdmin = userWithRole('super_admin');
    $report = Report::factory()->create([
        'target_type' => ReportTarget::USER->value,
        'target_id' => $superAdmin->id,
    ]);
    actingAs(userWithRole('moderator'));

    $this->post("/admin/reports/{$report->id}/ban-user")->assertForbidden();

    expect($superAdmin->fresh()->status)->toBe(UserStatus::ACTIVE);
});

it('hides account actions on the profile of a peer', function (): void {
    $peer = userWithRole('moderator');
    actingAs(userWithRole('moderator'));

    $this->get("/admin/users/{$peer->id}")
        ->assertOk()
        ->assertDontSee(route('admin.users.suspend', $peer), false);
});
