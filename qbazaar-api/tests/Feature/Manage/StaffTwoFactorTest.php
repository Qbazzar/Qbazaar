<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\StaffTwoFactor;
use App\Models\User;
use App\Services\Admin\TwoFactor\StaffTwoFactorService;
use App\Services\Admin\TwoFactor\Totp;
use App\Services\Admin\TwoFactor\TrustedBrowser;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);

    $this->seed(RolesAndPermissionsSeeder::class);
});

function tfaStaff(string $role = 'moderator', string $email = 'tfa@qbazaar.qa'): User
{
    return User::factory()
        ->create(['email' => $email, 'password' => Hash::make('correct-horse'), 'status' => UserStatus::ACTIVE])
        ->assignRole($role);
}

function tfaCode(User $user, int $offsetSeconds = 0): string
{
    $secret = StaffTwoFactor::query()->findOrFail($user->id)->secret;

    return app(Totp::class)->codeFor($secret, now()->getTimestamp() + $offsetSeconds);
}

/** @return list<string> the plain recovery codes */
function tfaEnable(User $user): array
{
    $service = app(StaffTwoFactorService::class);
    $service->begin($user);

    return $service->confirm($user, tfaCode($user));
}

function tfaPassword(object $test, string $email = 'tfa@qbazaar.qa'): mixed
{
    return $test->post('/admin/login', ['email' => $email, 'password' => 'correct-horse']);
}

describe('enrolment', function (): void {
    it('walks a staff member from start to confirmed, showing the recovery codes once', function (string $role): void {
        $user = tfaStaff($role);
        actingAs($user);

        $this->get(route('admin.profile.two-factor.show'))->assertOk()->assertSee(__('admin.two_factor.setup.start'));

        $this->post(route('admin.profile.two-factor.store'))->assertRedirect(route('admin.profile.two-factor.show'));
        $secret = StaffTwoFactor::query()->findOrFail($user->id)->secret;

        $this->get(route('admin.profile.two-factor.show'))
            ->assertOk()
            ->assertSee(trim(chunk_split($secret, 4, ' ')))
            ->assertSee('otpauth://totp/', false);

        $this->post(route('admin.profile.two-factor.confirm'), ['code' => tfaCode($user)])
            ->assertRedirect(route('admin.profile.two-factor.show'))
            ->assertSessionHas('two_factor_recovery_codes');

        $codes = session('two_factor_recovery_codes');
        $this->get(route('admin.profile.two-factor.show'))->assertOk()->assertSee($codes[0])->assertDontSee($secret);
        $this->get(route('admin.profile.two-factor.show'))->assertOk()->assertDontSee($codes[0]);

        $credential = StaffTwoFactor::query()->findOrFail($user->id);
        expect($credential->isConfirmed())->toBeTrue()
            ->and($credential->recovery_codes)->toHaveCount((int) config('qbazaar.admin.two_factor.recovery_codes'))
            ->and($credential->recovery_codes)->not->toContain($codes[0])
            ->and(Activity::query()->where('event', 'admin.two_factor.enabled')->where('causer_id', $user->id)->exists())->toBeTrue();
    })->with(['super_admin', 'moderator', 'support']);

    it('refuses a wrong first code and stays pending', function (): void {
        $user = tfaStaff();
        actingAs($user);
        $this->post(route('admin.profile.two-factor.store'));

        $this->post(route('admin.profile.two-factor.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors(['code' => __('admin.two_factor.errors.invalid_code')]);

        expect(StaffTwoFactor::query()->findOrFail($user->id)->isConfirmed())->toBeFalse();
    });

    it('validates the confirmation code field', function (mixed $code): void {
        actingAs(tfaStaff());
        $this->post(route('admin.profile.two-factor.store'));

        $this->post(route('admin.profile.two-factor.confirm'), ['code' => $code])->assertSessionHasErrors('code');
    })->with(['empty' => [''], 'too long' => [str_repeat('1', 17)], 'array' => [['1']]]);

    it('never replaces a confirmed credential by starting again', function (): void {
        $user = tfaStaff();
        tfaEnable($user);
        $secret = StaffTwoFactor::query()->findOrFail($user->id)->secret;
        actingAs($user);

        $this->post(route('admin.profile.two-factor.store'))
            ->assertSessionHasErrors(['code' => __('admin.two_factor.errors.already_enabled')]);

        expect(StaffTwoFactor::query()->findOrFail($user->id)->secret)->toBe($secret);
    });

    it('stores the secret encrypted', function (): void {
        $user = tfaStaff();
        tfaEnable($user);

        $raw = (string) DB::table('staff_two_factor')->where('user_id', $user->id)->value('secret');

        expect($raw)->not->toBe(StaffTwoFactor::query()->findOrFail($user->id)->secret)
            ->and(strlen($raw))->toBeGreaterThan(40);
    });
});

describe('sign-in', function (): void {
    it('asks for the code after the password and only then signs in', function (): void {
        $user = tfaStaff();
        tfaEnable($user);
        $this->travel(31)->seconds();

        tfaPassword($this)->assertRedirect(route('admin.two-factor.challenge'));
        $this->assertGuest();

        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get(route('admin.two-factor.challenge'))->assertOk()->assertSee(__('admin.two_factor.challenge.intro'));

        $this->post(route('admin.two-factor.verify'), ['code' => tfaCode($user)])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    });

    it('signs staff without two-factor straight in', function (): void {
        tfaStaff();

        tfaPassword($this)->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
    });

    it('accepts each code once, so an observed code cannot be replayed', function (): void {
        $user = tfaStaff();
        tfaEnable($user);
        $this->travel(31)->seconds();
        $code = tfaCode($user);

        tfaPassword($this);
        $this->post(route('admin.two-factor.verify'), ['code' => $code])->assertRedirect(route('admin.dashboard'));
        $this->post(route('admin.logout'));

        tfaPassword($this);
        $this->post(route('admin.two-factor.verify'), ['code' => $code])
            ->assertSessionHasErrors(['code' => __('admin.two_factor.errors.invalid_code')]);
        $this->assertGuest();
    });

    it('spends a recovery code on use', function (): void {
        $user = tfaStaff();
        $codes = tfaEnable($user);

        tfaPassword($this);
        $this->post(route('admin.two-factor.verify'), ['recovery_code' => strtolower($codes[0])])->assertRedirect(route('admin.dashboard'));
        $this->post(route('admin.logout'));

        tfaPassword($this);
        $this->post(route('admin.two-factor.verify'), ['recovery_code' => $codes[0]])
            ->assertSessionHasErrors(['recovery_code' => __('admin.two_factor.errors.invalid_code')]);

        expect(StaffTwoFactor::query()->findOrFail($user->id)->remainingRecoveryCodes())->toBe(count($codes) - 1)
            ->and(Activity::query()->where('event', 'admin.two_factor.recovery_code_used')->count())->toBe(1);
    });

    it('locks the prompt per account after too many wrong codes', function (): void {
        config(['qbazaar.admin.two_factor.max_attempts' => 3]);
        $user = tfaStaff();
        tfaEnable($user);
        $this->travel(31)->seconds();
        tfaPassword($this);

        foreach (range(1, 3) as $attempt) {
            $this->post(route('admin.two-factor.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        }

        $this->post(route('admin.two-factor.verify'), ['code' => tfaCode($user)])
            ->assertSessionHasErrors(['code']);
        $this->assertGuest();
    });

    it('sends an expired prompt back to the password', function (): void {
        $user = tfaStaff();
        tfaEnable($user);
        tfaPassword($this);

        $this->travel((int) config('qbazaar.admin.two_factor.challenge_ttl_minutes') + 1)->minutes();

        $this->post(route('admin.two-factor.verify'), ['code' => tfaCode($user)])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email' => __('admin.two_factor.errors.challenge_expired')]);
        $this->assertGuest();
    });

    it('does not open the prompt without a pending sign-in', function (): void {
        $this->get(route('admin.two-factor.challenge'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.two-factor.verify'), ['code' => '123456'])->assertRedirect(route('admin.login'));
    });

    it('drops the pending sign-in when the account is suspended meanwhile', function (): void {
        $user = tfaStaff();
        tfaEnable($user);
        tfaPassword($this);
        $user->forceFill(['status' => UserStatus::SUSPENDED])->save();

        $this->post(route('admin.two-factor.verify'), ['code' => tfaCode($user, 30)])->assertRedirect(route('admin.login'));
        $this->assertGuest();
    });

    it('requires a code or a recovery code', function (): void {
        $user = tfaStaff();
        tfaEnable($user);
        tfaPassword($this);

        $this->post(route('admin.two-factor.verify'), [])->assertSessionHasErrors(['code', 'recovery_code']);
        $this->post(route('admin.two-factor.verify'), ['code' => str_repeat('1', 17)])->assertSessionHasErrors('code');
    });
});

describe('trusted browsers', function (): void {
    it('skips the prompt on a trusted browser until the credential changes', function (): void {
        $user = tfaStaff();
        tfaEnable($user);
        $this->travel(31)->seconds();

        tfaPassword($this);
        $response = $this->post(route('admin.two-factor.verify'), ['code' => tfaCode($user), 'trust_browser' => '1']);
        $response->assertCookie(TrustedBrowser::COOKIE);
        $trust = $response->getCookie(TrustedBrowser::COOKIE)->getValue();
        $this->post(route('admin.logout'));

        $this->withCookie(TrustedBrowser::COOKIE, $trust);
        tfaPassword($this)->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->post(route('admin.logout'));

        // Turning it off and on again bumps the device version and revokes the cookie.
        $service = app(StaffTwoFactorService::class);
        $service->disable($user, $user);
        tfaEnable($user);

        $this->withCookie(TrustedBrowser::COOKIE, $trust);
        tfaPassword($this)->assertRedirect(route('admin.two-factor.challenge'));
        $this->assertGuest();
    });

    it('ignores a trust cookie minted for another staff member', function (): void {
        $owner = tfaStaff('moderator', 'owner@qbazaar.qa');
        tfaEnable($owner);
        $other = tfaStaff('moderator', 'other@qbazaar.qa');
        tfaEnable($other);
        $forged = app(TrustedBrowser::class)->payloadFor($owner, StaffTwoFactor::query()->findOrFail($owner->id), now()->addDay());

        $this->withCookie(TrustedBrowser::COOKIE, $forged);
        tfaPassword($this, 'other@qbazaar.qa')->assertRedirect(route('admin.two-factor.challenge'));
    });
});

describe('enforcement', function (): void {
    beforeEach(fn () => config(['qbazaar.admin.two_factor.enforce' => true]));

    it('keeps a super admin without two-factor on the enrolment page', function (): void {
        actingAs(tfaStaff('super_admin'));

        $this->get('/admin')->assertRedirect(route('admin.profile.two-factor.show'));
        $this->get('/admin/users')->assertRedirect(route('admin.profile.two-factor.show'));
        $this->get(route('admin.profile.two-factor.show'))->assertOk();
        $this->get(route('admin.profile.edit'))->assertOk();
    });

    it('lets an enrolled super admin through', function (): void {
        $admin = tfaStaff('super_admin');
        tfaEnable($admin);
        actingAs($admin);

        $this->get('/admin')->assertOk();
    });

    it('leaves two-factor optional for moderators and support', function (string $role): void {
        actingAs(tfaStaff($role));

        $this->get('/admin')->assertOk();
    })->with(['moderator', 'support']);
});

describe('managing an enabled credential', function (): void {
    it('turns two-factor off only with the current password', function (): void {
        $user = tfaStaff();
        tfaEnable($user);
        actingAs($user);

        $this->delete(route('admin.profile.two-factor.destroy'), ['current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');
        expect(StaffTwoFactor::query()->whereKey($user->id)->exists())->toBeTrue();

        $this->delete(route('admin.profile.two-factor.destroy'), ['current_password' => 'correct-horse'])
            ->assertRedirect(route('admin.profile.two-factor.show'));
        expect(StaffTwoFactor::query()->whereKey($user->id)->exists())->toBeFalse()
            ->and(Activity::query()->where('event', 'admin.two_factor.disabled')->exists())->toBeTrue();
    });

    it('replaces the recovery codes with the current password, retiring the old ones', function (): void {
        $user = tfaStaff();
        $old = tfaEnable($user);
        actingAs($user);

        $this->post(route('admin.profile.two-factor.recovery-codes'), ['current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');

        $this->post(route('admin.profile.two-factor.recovery-codes'), ['current_password' => 'correct-horse'])
            ->assertSessionHas('two_factor_recovery_codes');
        $new = session('two_factor_recovery_codes');

        $service = app(StaffTwoFactorService::class);
        expect($new)->not->toBe($old)
            ->and($service->verifyLogin($user, $old[0], recovery: true))->toBeFalse()
            ->and($service->verifyLogin($user, $new[0], recovery: true))->toBeTrue();
    });

    it('cycles the remember-me token when the credential changes', function (): void {
        $user = tfaStaff();
        $user->forceFill(['remember_token' => 'old-token'])->save();

        tfaEnable($user);

        expect($user->fresh()->getRememberToken())->not->toBe('old-token');
    });
});

describe('resetting a colleague', function (): void {
    it('lets a super admin reset a junior colleague and logs it', function (): void {
        $moderator = tfaStaff('moderator', 'mod@qbazaar.qa');
        tfaEnable($moderator);
        $admin = tfaStaff('super_admin', 'boss@qbazaar.qa');
        actingAs($admin);

        $this->get(route('admin.users.show', $moderator))->assertOk()->assertSee(route('admin.users.two-factor.destroy', $moderator), false);
        $this->delete(route('admin.users.two-factor.destroy', $moderator))->assertSessionHas('status');

        expect(StaffTwoFactor::query()->whereKey($moderator->id)->exists())->toBeFalse()
            ->and(Activity::query()->where('event', 'admin.two_factor.reset')->where('causer_id', $admin->id)->where('subject_id', $moderator->id)->exists())->toBeTrue();
    });

    it('refuses the reset to staff without roles.manage', function (string $role): void {
        $target = tfaStaff('support', 'target@qbazaar.qa');
        tfaEnable($target);
        actingAs(tfaStaff($role, 'actor@qbazaar.qa'));

        $this->delete(route('admin.users.two-factor.destroy', $target))->assertForbidden();
        expect(StaffTwoFactor::query()->whereKey($target->id)->exists())->toBeTrue();
    })->with(['moderator', 'support']);

    it('refuses the reset of a peer or of yourself', function (): void {
        $peer = tfaStaff('super_admin', 'peer@qbazaar.qa');
        tfaEnable($peer);
        $admin = tfaStaff('super_admin', 'boss@qbazaar.qa');
        actingAs($admin);

        $this->delete(route('admin.users.two-factor.destroy', $peer))->assertForbidden();
        $this->delete(route('admin.users.two-factor.destroy', $admin))->assertForbidden();
        expect(StaffTwoFactor::query()->whereKey($peer->id)->exists())->toBeTrue();
    });

    it('offers a break-glass console reset', function (): void {
        $admin = tfaStaff('super_admin', 'boss@qbazaar.qa');
        tfaEnable($admin);

        $this->artisan('admin:two-factor-reset', ['email' => 'BOSS@qbazaar.qa'])->assertSuccessful();
        $this->artisan('admin:two-factor-reset', ['email' => 'nobody@qbazaar.qa'])->assertFailed();

        expect(StaffTwoFactor::query()->whereKey($admin->id)->exists())->toBeFalse();
    });
});

it('never writes codes or passwords into the audit log', function (): void {
    $user = tfaStaff();
    actingAs($user);
    $this->post(route('admin.profile.two-factor.store'));
    $code = tfaCode($user);
    $this->post(route('admin.profile.two-factor.confirm'), ['code' => $code]);
    $this->post(route('admin.profile.two-factor.recovery-codes'), ['current_password' => 'correct-horse']);

    $logged = Activity::query()->pluck('properties')->toJson();

    expect($logged)->not->toContain($code)->not->toContain('correct-horse');
});
