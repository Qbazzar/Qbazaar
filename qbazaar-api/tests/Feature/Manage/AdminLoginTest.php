<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);

    $this->seed(RolesAndPermissionsSeeder::class);
});

function staffAccount(array $attributes = []): User
{
    return User::factory()
        ->create([
            'email' => 'staff@qbazaar.qa',
            'password' => Hash::make('correct-horse'),
            'status' => UserStatus::ACTIVE,
            ...$attributes,
        ])
        ->assignRole('moderator');
}

it('signs in an active staff member', function (): void {
    staffAccount();

    $this->post('/admin/login', ['email' => 'staff@qbazaar.qa', 'password' => 'correct-horse'])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticated();
});

it('locks out an email and IP after too many failed attempts', function (): void {
    config(['qbazaar.admin.login_max_attempts' => 3]);
    staffAccount();

    foreach (range(1, 3) as $attempt) {
        $this->post('/admin/login', ['email' => 'staff@qbazaar.qa', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    $this->post('/admin/login', ['email' => 'staff@qbazaar.qa', 'password' => 'correct-horse'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('counts failed attempts per IP', function (): void {
    config(['qbazaar.admin.login_max_attempts' => 1]);
    staffAccount();

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->post('/admin/login', ['email' => 'staff@qbazaar.qa', 'password' => 'wrong']);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->post('/admin/login', ['email' => 'staff@qbazaar.qa', 'password' => 'correct-horse'])
        ->assertRedirect(route('admin.dashboard'));
});

it('refuses sign-in for a suspended staff member', function (): void {
    staffAccount(['status' => UserStatus::SUSPENDED]);

    $this->post('/admin/login', ['email' => 'staff@qbazaar.qa', 'password' => 'correct-horse'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

it('refuses sign-in for non-staff accounts', function (): void {
    User::factory()->create(['email' => 'buyer@qbazaar.qa', 'password' => Hash::make('correct-horse')]);

    $this->post('/admin/login', ['email' => 'buyer@qbazaar.qa', 'password' => 'correct-horse'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('ends the session of a staff member suspended after signing in', function (): void {
    $staff = staffAccount();
    actingAs($staff);
    $staff->forceFill(['status' => UserStatus::SUSPENDED])->save();

    $this->get('/admin/ads')->assertRedirect(route('admin.login'));

    $this->assertGuest();
});

it('forces the seeded admin to replace the default password', function (): void {
    $admin = User::query()->where('email', 'admin@qbazaar.qa')->firstOrFail();

    expect($admin->must_change_password)->toBeTrue();

    $this->post('/admin/login', ['email' => 'admin@qbazaar.qa', 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard'));

    $this->get('/admin/ads')->assertRedirect(route('admin.profile.edit'));
    $this->get('/admin/profile')->assertOk();

    $profile = ['full_name' => $admin->full_name, 'email' => $admin->email];

    $this->put('/admin/profile', $profile)->assertSessionHasErrors('password');
    $this->put('/admin/profile', [...$profile, 'password' => 'password', 'password_confirmation' => 'password'])
        ->assertSessionHasErrors('password');

    $this->put('/admin/profile', [...$profile, 'password' => 'a-new-strong-pass', 'password_confirmation' => 'a-new-strong-pass'])
        ->assertSessionHasNoErrors();

    expect($admin->fresh()->must_change_password)->toBeFalse();
    $this->get('/admin/ads')->assertOk();
});
