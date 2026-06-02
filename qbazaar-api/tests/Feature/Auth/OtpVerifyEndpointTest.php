<?php

declare(strict_types=1);

use App\Models\OtpCode;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    // These cases exercise the OTP attempt-burn logic (max 3 attempts) and so
    // make up to 4 verify calls. The HTTP `throttle:otp` limiter is 3/min, an
    // orthogonal concern that's covered by OtpSend/OtpResend tests — disable it
    // here so the verify logic isn't masked by a 429.
    $this->withoutMiddleware(ThrottleRequests::class);
    Notification::fake();
});

/**
 * Helper: issue an OTP for a phone and return the raw 6-digit code by
 * reaching into the service directly.
 */
function issueOtp(string $phone): string
{
    /** @var OtpService $service */
    $service = app(OtpService::class);

    return $service->issue($phone)->rawCode;
}

it('flips phone_verified to true for the matching user on the right code', function (): void {
    $user = User::factory()->create(['phone' => '+97455123456', 'phone_verified' => false]);

    $code = issueOtp('+97455123456');

    postJson('/api/v1/auth/verify-otp', [
        'phone' => '+97455123456',
        'code' => $code,
    ])
        ->assertOk()
        ->assertJson(
            fn ($json) => $json
                ->where('success', true)
                ->where('data.phone_verified', true)
                ->etc(),
        );

    expect($user->fresh()->phone_verified)->toBeTrue();
});

it('returns AUTH_004 (410) when the OTP has expired', function (): void {
    $phone = '+97455123456';

    OtpCode::query()->create([
        'phone' => $phone,
        'code_hash' => Hash::make('482915'),
        'attempts' => 0,
        'expires_at' => Carbon::now()->subMinute(),
        'used_at' => null,
    ]);

    postJson('/api/v1/auth/verify-otp', ['phone' => $phone, 'code' => '482915'])
        ->assertStatus(410)
        ->assertJson(
            fn ($json) => $json
                ->where('error.code', 'AUTH_004')
                ->etc(),
        );
});

it('returns AUTH_005 (422) for a wrong code', function (): void {
    issueOtp('+97455123456');

    postJson('/api/v1/auth/verify-otp', ['phone' => '+97455123456', 'code' => '999999'])
        ->assertStatus(422)
        ->assertJson(
            fn ($json) => $json
                ->where('error.code', 'AUTH_005')
                ->etc(),
        );
});

it('burns the row after max attempts and refuses further tries with AUTH_005', function (): void {
    issueOtp('+97455123456');

    foreach (range(1, 3) as $i) {
        postJson('/api/v1/auth/verify-otp', ['phone' => '+97455123456', 'code' => '000000'])
            ->assertStatus(422)
            ->assertJson(fn ($json) => $json->where('error.code', 'AUTH_005')->etc());
    }

    // Row should now be burnt: a subsequent attempt with the (notionally-correct)
    // code still fails because the row is used_at.
    expect(OtpCode::query()->where('phone', '+97455123456')->whereNull('used_at')->count())->toBe(0);

    postJson('/api/v1/auth/verify-otp', ['phone' => '+97455123456', 'code' => '000000'])
        ->assertStatus(422)
        ->assertJson(fn ($json) => $json->where('error.code', 'AUTH_005')->etc());
});

it('returns AUTH_005 when no OTP has ever been issued', function (): void {
    postJson('/api/v1/auth/verify-otp', ['phone' => '+97455123456', 'code' => '482915'])
        ->assertStatus(422)
        ->assertJson(fn ($json) => $json->where('error.code', 'AUTH_005')->etc());
});

it('only matches the latest active OTP when send-otp was called twice', function (): void {
    $first = issueOtp('+97455123456');
    $second = issueOtp('+97455123456');

    // The first code is now soft-burnt and must not verify.
    postJson('/api/v1/auth/verify-otp', ['phone' => '+97455123456', 'code' => $first])
        ->assertStatus(422)
        ->assertJson(fn ($json) => $json->where('error.code', 'AUTH_005')->etc());

    // The newest code still verifies.
    postJson('/api/v1/auth/verify-otp', ['phone' => '+97455123456', 'code' => $second])
        ->assertOk();
});

it('validates the request shape', function (): void {
    postJson('/api/v1/auth/verify-otp', [])
        ->assertStatus(422)
        ->assertJson(
            fn ($json) => $json
                ->where('error.code', 'VALIDATION_FAILED')
                ->has('error.details.phone')
                ->has('error.details.code')
                ->etc(),
        );
});
