<?php

declare(strict_types=1);

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

use Tests\Concerns\RequestsReauthCodes;

uses(RefreshDatabase::class, RequestsReauthCodes::class);

const STEP_UP_NEW_PASSWORD = 'NewStr0ng!Pass#1';

beforeEach(function (): void {
    Notification::fake();

    $this->passwordless = User::factory()->create(['password' => null]);
    $this->withPassword = User::factory()->create(['password' => Hash::make('OldStr0ng!Pass')]);
});

/**
 * @return array<string, string>
 */
function newPasswordWithCode(string $code): array
{
    return ['reauth_code' => $code, 'password' => STEP_UP_NEW_PASSWORD, 'password_confirmation' => STEP_UP_NEW_PASSWORD];
}

it('lets an account without a password set its first one with an emailed code', function (): void {
    Sanctum::actingAs($this->passwordless, ['*']);
    getJson('/api/v1/me')->assertOk()->assertJsonPath('data.has_password', false);

    putJson('/api/v1/account/password', newPasswordWithCode($this->requestReauthCode($this->passwordless)))
        ->assertNoContent();

    expect(Hash::check(STEP_UP_NEW_PASSWORD, (string) $this->passwordless->fresh()->password))->toBeTrue();
    getJson('/api/v1/me')->assertOk()->assertJsonPath('data.has_password', true);
});

it('lets an account with a password change it with an emailed code instead', function (): void {
    Sanctum::actingAs($this->withPassword, ['*']);

    putJson('/api/v1/account/password', newPasswordWithCode($this->requestReauthCode($this->withPassword)))
        ->assertNoContent();

    expect(Hash::check(STEP_UP_NEW_PASSWORD, (string) $this->withPassword->fresh()->password))->toBeTrue();
});

it('keeps the current session and revokes the other sessions', function (): void {
    $current = $this->passwordless->createToken('current');
    $other = $this->passwordless->createToken('other');
    $refresh = RefreshToken::query()->create([
        'id' => strtolower((string) Str::ulid()),
        'user_id' => $this->passwordless->id,
        'token_hash' => Hash::make('some_old_token'),
        'expires_at' => now()->addDays(30),
    ]);
    $this->withToken($current->plainTextToken);

    putJson('/api/v1/account/password', newPasswordWithCode($this->requestReauthCode($this->passwordless)))
        ->assertNoContent();

    expect(PersonalAccessToken::query()->find($current->accessToken->getKey()))->not->toBeNull()
        ->and(PersonalAccessToken::query()->find($other->accessToken->getKey()))->toBeNull()
        ->and($refresh->fresh()->used_at)->not->toBeNull();
});

it('rejects a wrong code with ACCOUNT_001 and keeps the password', function (): void {
    Sanctum::actingAs($this->withPassword, ['*']);
    $code = $this->requestReauthCode($this->withPassword);

    putJson('/api/v1/account/password', newPasswordWithCode($this->wrongReauthCode($code)))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ACCOUNT_001');

    expect(Hash::check('OldStr0ng!Pass', (string) $this->withPassword->fresh()->password))->toBeTrue();
});

it('rejects an expired code', function (): void {
    Sanctum::actingAs($this->passwordless, ['*']);
    $code = $this->requestReauthCode($this->passwordless);

    $this->travel(11)->minutes();

    putJson('/api/v1/account/password', newPasswordWithCode($code))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ACCOUNT_001');

    expect($this->passwordless->fresh()->hasPassword())->toBeFalse();
});

it('accepts each code only once', function (): void {
    Sanctum::actingAs($this->passwordless, ['*']);
    $code = $this->requestReauthCode($this->passwordless);

    putJson('/api/v1/account/password', newPasswordWithCode($code))->assertNoContent();

    putJson('/api/v1/account/password', [...newPasswordWithCode($code), 'password' => 'Other!Pass#2', 'password_confirmation' => 'Other!Pass#2'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ACCOUNT_001');

    expect(Hash::check(STEP_UP_NEW_PASSWORD, (string) $this->passwordless->fresh()->password))->toBeTrue();
});

it('refuses the current password and a code together without spending the code', function (): void {
    Sanctum::actingAs($this->withPassword, ['*']);
    $code = $this->requestReauthCode($this->withPassword);

    putJson('/api/v1/account/password', [...newPasswordWithCode($code), 'current_password' => 'OldStr0ng!Pass'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonPath('error.details.current_password.0', __('users.step_up.only_one'));

    putJson('/api/v1/account/password', newPasswordWithCode($code))->assertNoContent();
});

it('refuses a change confirmed with neither the current password nor a code', function (): void {
    Sanctum::actingAs($this->withPassword, ['*']);

    putJson('/api/v1/account/password', ['password' => STEP_UP_NEW_PASSWORD, 'password_confirmation' => STEP_UP_NEW_PASSWORD])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['current_password', 'reauth_code'], 'error.details');
});

it('tells an account without a password to confirm with the code', function (): void {
    Sanctum::actingAs($this->passwordless, ['*']);

    putJson('/api/v1/account/password', ['current_password' => 'Guess!Pass1', 'password' => STEP_UP_NEW_PASSWORD, 'password_confirmation' => STEP_UP_NEW_PASSWORD])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonPath('error.details.current_password.0', __('users.step_up.no_password'));

    putJson('/api/v1/account/password', ['password' => STEP_UP_NEW_PASSWORD, 'password_confirmation' => STEP_UP_NEW_PASSWORD])
        ->assertStatus(422)
        ->assertJsonPath('error.details.reauth_code.0', __('users.step_up.code_required'));

    expect($this->passwordless->fresh()->hasPassword())->toBeFalse();
});

it('validates the new password before spending the code', function (): void {
    Sanctum::actingAs($this->passwordless, ['*']);
    $code = $this->requestReauthCode($this->passwordless);

    putJson('/api/v1/account/password', ['reauth_code' => $code, 'password' => 'weak', 'password_confirmation' => 'weak'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password'], 'error.details');

    putJson('/api/v1/account/password', newPasswordWithCode($code))->assertNoContent();
});

it('caps code attempts per hour like the email change, but not password attempts', function (): void {
    config()->set('qbazaar.account.contact_change_attempts_per_hour', 2);
    Sanctum::actingAs($this->withPassword, ['*']);

    putJson('/api/v1/account/password', newPasswordWithCode('000000'))->assertStatus(422);
    putJson('/api/v1/account/password', newPasswordWithCode('000000'))->assertStatus(422);
    putJson('/api/v1/account/password', newPasswordWithCode('000000'))->assertStatus(429);

    putJson('/api/v1/account/password', ['current_password' => 'Wrong!Pass1', 'password' => STEP_UP_NEW_PASSWORD, 'password_confirmation' => STEP_UP_NEW_PASSWORD])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'USER_004');
});
