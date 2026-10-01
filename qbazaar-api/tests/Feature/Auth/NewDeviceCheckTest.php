<?php

declare(strict_types=1);

use App\Models\OtpCode;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Notifications\OtpNotification;
use App\Notifications\SecurityAlertNotification;
use App\Services\Auth\DeviceFingerprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

const TRUSTED_TEST_DEVICE_ID = 'known-device-0123456789';
const UNKNOWN_TEST_DEVICE_ID = 'new-device-0123456789ab';

beforeEach(function (): void {
    Cache::flush();
    Notification::fake();
    config(['qbazaar.auth.new_device_check.enabled' => true]);

    $this->user = User::factory()->create([
        'email' => 'owner@example.qa',
        'phone' => '+97455222333',
        'password' => Hash::make('Str0ng!Pass'),
        'phone_verified' => false,
        'last_login_at' => Carbon::now()->subDay(),
    ]);

    TrustedDevice::query()->create([
        'user_id' => $this->user->id,
        'device_hash' => hash('sha256', 'device-id|' . TRUSTED_TEST_DEVICE_ID),
        'label' => 'ios',
        'last_ip' => '127.0.0.1',
        'last_used_at' => Carbon::now()->subDay(),
    ]);
});

function passwordLogin(string $deviceId): TestResponse
{
    return postJson('/api/v1/auth/login', [
        'identifier' => 'owner@example.qa',
        'password' => 'Str0ng!Pass',
    ], [DeviceFingerprintService::HEADER => $deviceId]);
}

function smsCodeSentTo(User $user): string
{
    $code = null;

    Notification::assertSentTo($user, OtpNotification::class, function (OtpNotification $notification) use (&$code): bool {
        $code = $notification->code;

        return true;
    });

    return (string) $code;
}

it('lets a trusted device straight in', function (): void {
    passwordLogin(TRUSTED_TEST_DEVICE_ID)
        ->assertOk()
        ->assertJsonStructure(['data' => ['user', 'tokens' => ['access_token', 'refresh_token']]]);

    Notification::assertNothingSent();
});

it('holds a new device at an SMS challenge without issuing tokens', function (): void {
    $response = passwordLogin(UNKNOWN_TEST_DEVICE_ID)
        ->assertStatus(202)
        ->assertJsonPath('data.device_verification_required', true)
        ->assertJsonPath('data.sent_to', '+974*****333')
        ->assertJsonMissingPath('data.tokens');

    expect(strlen((string) $response->json('data.challenge_token')))->toBe(64)
        ->and(PersonalAccessToken::query()->count())->toBe(0)
        ->and(OtpCode::query()->where('recipient', '+97455222333')->sole()->purpose->value)->toBe('new_device');

    smsCodeSentTo($this->user);
});

it('issues tokens, trusts the device and alerts the user once the SMS code is right', function (): void {
    $token = passwordLogin(UNKNOWN_TEST_DEVICE_ID)->json('data.challenge_token');

    postJson('/api/v1/auth/device/verify', ['challenge_token' => $token, 'code' => smsCodeSentTo($this->user)])
        ->assertOk()
        ->assertJsonPath('data.user.id', $this->user->id)
        ->assertJsonPath('data.user.phone_verified', true)
        ->assertJsonStructure(['data' => ['tokens' => ['access_token', 'refresh_token']]]);

    expect(TrustedDevice::query()->where('user_id', $this->user->id)->count())->toBe(2);

    Notification::assertSentTo(
        $this->user,
        SecurityAlertNotification::class,
        fn (SecurityAlertNotification $n): bool => $n->toArray($this->user)['category'] === 'security.new_device',
    );

    passwordLogin(UNKNOWN_TEST_DEVICE_ID)->assertOk();
});

it('cannot reuse a challenge once it is completed', function (): void {
    $token = passwordLogin(UNKNOWN_TEST_DEVICE_ID)->json('data.challenge_token');
    $code = smsCodeSentTo($this->user);

    postJson('/api/v1/auth/device/verify', ['challenge_token' => $token, 'code' => $code])->assertOk();

    postJson('/api/v1/auth/device/verify', ['challenge_token' => $token, 'code' => $code])
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'AUTH_012');
});

it('rejects a wrong SMS code', function (): void {
    $token = passwordLogin(UNKNOWN_TEST_DEVICE_ID)->json('data.challenge_token');
    $wrong = smsCodeSentTo($this->user) === '000000' ? '111111' : '000000';

    postJson('/api/v1/auth/device/verify', ['challenge_token' => $token, 'code' => $wrong])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'AUTH_005');

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('rejects an unknown challenge token', function (): void {
    postJson('/api/v1/auth/device/verify', ['challenge_token' => str_repeat('a', 64), 'code' => '123456'])
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'AUTH_012');
});

it('validates the verify payload', function (): void {
    postJson('/api/v1/auth/device/verify', ['challenge_token' => 'short', 'code' => 'abc'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['challenge_token', 'code'], 'error.details');
});

it('does not send a second SMS inside the cooldown', function (): void {
    passwordLogin(UNKNOWN_TEST_DEVICE_ID)->assertStatus(202);

    passwordLogin(UNKNOWN_TEST_DEVICE_ID)
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'AUTH_006');
});

it('trusts the device an account was created on', function (): void {
    postJson('/api/v1/auth/register', [
        'full_name' => 'New Person',
        'email' => 'new@example.qa',
        'phone' => '+97455999000',
        'password' => 'Str0ng!Pass',
        'account_type' => 'private',
        'accepted_terms' => true,
    ], [DeviceFingerprintService::HEADER => UNKNOWN_TEST_DEVICE_ID])->assertCreated();

    postJson('/api/v1/auth/login', [
        'identifier' => 'new@example.qa',
        'password' => 'Str0ng!Pass',
    ], [DeviceFingerprintService::HEADER => UNKNOWN_TEST_DEVICE_ID])->assertOk();
});

it('skips the challenge when the check is turned off', function (): void {
    config(['qbazaar.auth.new_device_check.enabled' => false]);

    passwordLogin(UNKNOWN_TEST_DEVICE_ID)->assertOk();

    Notification::assertSentTo($this->user, SecurityAlertNotification::class);
});
