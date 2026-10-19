<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CountsPageQueries;

uses(RefreshDatabase::class, CountsPageQueries::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create()->assignRole('super_admin');
});

/** @return list<string> */
function rolePermissions(string $role): array
{
    return Role::findByName($role, 'web')->permissions()->pluck('name')->sort()->values()->all();
}

/**
 * The matrix as the form posts it, starting from what the roles hold now.
 *
 * @return array<string, list<string>>
 */
function currentMatrix(): array
{
    return ['moderator' => rolePermissions('moderator'), 'support' => rolePermissions('support')];
}

it('shows super_admin the matrix with super_admin locked and reserved permissions disabled', function (): void {
    actingAs($this->admin);

    $html = $this->get(route('admin.roles.permissions.edit'))
        ->assertOk()
        ->assertSee(__('admin.permission_matrix.title'))
        ->assertSee(__('admin.permission_matrix.locked'))
        ->assertSee(__('admin.permission_matrix.permissions.conversations_view'))
        ->assertSee('name="permissions[moderator][]" value="conversations.view"', false)
        ->getContent();

    // Only the editable roles get checkboxes, and the reserved permissions are disabled for them.
    expect($html)->not->toContain('permissions[super_admin]')
        ->and(substr_count($html, 'value="roles.manage"'))->toBe(2)
        ->and(preg_match_all('/value="roles\.manage"\s+disabled/', $html))->toBe(2);
});

it('refuses the matrix to every role but super_admin', function (string $role): void {
    actingAs(User::factory()->create()->assignRole($role));

    $this->get(route('admin.roles.permissions.edit'))->assertForbidden();
    $this->put(route('admin.roles.permissions.update'), ['permissions' => ['support' => ['users.view']]])->assertForbidden();

    expect(rolePermissions('support'))->toContain('notifications.broadcast');
})->with(['moderator', 'support']);

it('keeps the matrix closed to a role that somehow holds roles.manage', function (): void {
    // Mutation guard: the route checks the role as well as the permission.
    Role::findByName('moderator', 'web')->givePermissionTo('roles.manage');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs(User::factory()->create()->assignRole('moderator'));

    $this->get(route('admin.roles.index'))->assertForbidden();
    $this->get(route('admin.roles.permissions.edit'))->assertForbidden();
    $this->put(route('admin.roles.permissions.update'), ['permissions' => ['moderator' => ['finance.manage']]])->assertForbidden();

    expect(rolePermissions('moderator'))->not->toContain('finance.manage');
});

it('saves granted and revoked permissions per role and audits each change', function (): void {
    actingAs($this->admin);
    $matrix = currentMatrix();
    $matrix['support'][] = 'conversations.view';
    $matrix['support'] = array_values(array_diff($matrix['support'], ['notifications.broadcast']));

    $this->put(route('admin.roles.permissions.update'), ['permissions' => $matrix])
        ->assertRedirect(route('admin.roles.permissions.edit'))
        ->assertSessionHas('status', __('admin.permission_matrix.flash.saved'));

    expect(rolePermissions('support'))->toContain('conversations.view')->not->toContain('notifications.broadcast');

    $audit = Activity::query()->where('event', 'admin.roles.permissions_changed')->sole();
    expect($audit->causer_id)->toBe($this->admin->id)
        ->and($audit->properties['role'])->toBe('support')
        ->and($audit->properties['granted'])->toBe(['conversations.view'])
        ->and($audit->properties['revoked'])->toBe(['notifications.broadcast']);
});

it('applies a change to staff with the role right away', function (): void {
    $support = User::factory()->create()->assignRole('support');
    actingAs($support);
    $this->get(route('admin.conversations.index'))->assertForbidden();

    actingAs($this->admin);
    $matrix = currentMatrix();
    $matrix['support'][] = 'conversations.view';
    $this->put(route('admin.roles.permissions.update'), ['permissions' => $matrix]);

    actingAs($support->fresh());
    $this->get(route('admin.conversations.index'))->assertOk();
});

it('empties a role whose boxes are all unticked, and leaves super_admin with everything', function (): void {
    actingAs($this->admin);

    $this->put(route('admin.roles.permissions.update'), ['permissions' => ['moderator' => rolePermissions('moderator')]])->assertSessionHasNoErrors();

    expect(rolePermissions('support'))->toBe([])
        ->and(rolePermissions('super_admin'))->toBe(Permission::query()->pluck('name')->sort()->values()->all());
});

it('refuses reserved permissions, unknown permissions and the super_admin column', function (array $permissions): void {
    actingAs($this->admin);
    $before = currentMatrix();

    $this->put(route('admin.roles.permissions.update'), ['permissions' => $permissions])->assertSessionHasErrors();

    expect(currentMatrix())->toBe($before)
        ->and(Activity::query()->where('event', 'admin.roles.permissions_changed')->count())->toBe(0);
})->with([
    'roles.manage to moderator' => [['moderator' => ['users.view', 'roles.manage']]],
    'integrations.manage to support' => [['support' => ['integrations.manage']]],
    'a permission that does not exist' => [['support' => ['users.everything']]],
    'editing super_admin' => [['super_admin' => ['users.view']]],
]);

it('does not let a super_admin remove their own super_admin role', function (): void {
    actingAs($this->admin);

    $this->post(route('admin.users.roles', $this->admin), ['roles' => ['moderator']])->assertForbidden();

    expect($this->admin->fresh()->hasRole('super_admin'))->toBeTrue();
});

it('links the matrix from the roles page', function (): void {
    actingAs($this->admin);

    $this->get(route('admin.roles.index'))->assertOk()->assertSee(route('admin.roles.permissions.edit'), false);
});

it('renders the matrix with a fixed number of queries as roles are added', function (): void {
    actingAs($this->admin);
    $uri = route('admin.roles.permissions.edit');
    $few = $this->steadyQueryCount($uri);

    foreach (['verifier', 'finance', 'editor'] as $name) {
        Role::findOrCreate($name, 'web')->givePermissionTo(['users.view', 'ads.view']);
    }

    expect($this->steadyQueryCount($uri))->toBe($few);
});
