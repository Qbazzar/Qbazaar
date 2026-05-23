<?php

declare(strict_types=1);

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    RateLimiter::clear('auth|127.0.0.1');

    $this->user = User::factory()->create([
        'email' => 'reset@example.qa',
        'password' => Hash::make('Old!Pass1234'),
    ]);
});

it('returns 200 + sets the new password on a valid token', function (): void {
    $token = Password::broker()->createToken($this->user);

    $payload = [
        'email' => 'reset@example.qa',
        'token' => $token,
        'password' => 'New!Pass5678',
        'password_confirmation' => 'New!Pass5678',
    ];

    postJson('/api/v1/auth/reset-password', $payload)
        ->assertOk()
        ->assertJson(
            fn ($json) => $json
                ->where('success', true)
                ->where('data.message_key', 'messages.auth.password_reset_success')
                ->etc(),
        );

    expect(Hash::check('New!Pass5678', $this->user->fresh()->password))->toBeTrue();
});

it('returns VALIDATION_FAILED with details on a weak password', function (): void {
    $token = Password::broker()->createToken($this->user);

    postJson('/api/v1/auth/reset-password', [
        'email' => 'reset@example.qa',
        'token' => $token,
        'password' => 'weakpass', // no upper, no digit, no symbol
        'password_confirmation' => 'weakpass',
    ])
        ->assertStatus(422)
        ->assertJson(
            fn ($json) => $json
                ->where('success', false)
                ->where('error.code', 'VALIDATION_FAILED')
                ->has('error.details.password')
                ->etc(),
        );

    // Password must not have changed.
    expect(Hash::check('Old!Pass1234', $this->user->fresh()->password))->toBeTrue();
});

it('returns VALIDATION_FAILED on an invalid/expired token', function (): void {
    postJson('/api/v1/auth/reset-password', [
        'email' => 'reset@example.qa',
        'token' => 'definitely-not-a-real-token',
        'password' => 'New!Pass5678',
        'password_confirmation' => 'New!Pass5678',
    ])
        ->assertStatus(422)
        ->assertJson(
            fn ($json) => $json
                ->where('error.code', 'VALIDATION_FAILED')
                ->has('error.details.token')
                ->etc(),
        );
});

it('burns every refresh token AND every Sanctum PAT on successful reset', function (): void {
    // Seed two live refresh tokens for the user — one previously used, one fresh.
    RefreshToken::query()->create([
        'id' => strtolower((string) Str::ulid()),
        'user_id' => $this->user->id,
        'token_hash' => Hash::make('rt_pre1'),
        'expires_at' => now()->addDays(30),
        'used_at' => null,
    ]);
    RefreshToken::query()->create([
        'id' => strtolower((string) Str::ulid()),
        'user_id' => $this->user->id,
        'token_hash' => Hash::make('rt_pre2'),
        'expires_at' => now()->addDays(30),
        'used_at' => null,
    ]);

    // And two Sanctum PATs.
    $this->user->createToken('api');
    $this->user->createToken('api');

    $beforeRefreshActive = RefreshToken::query()
        ->where('user_id', $this->user->id)
        ->whereNull('used_at')
        ->count();
    $beforeSanctum = PersonalAccessToken::query()
        ->where('tokenable_id', $this->user->id)
        ->count();

    expect($beforeRefreshActive)->toBe(2)
        ->and($beforeSanctum)->toBe(2);

    $token = Password::broker()->createToken($this->user);

    postJson('/api/v1/auth/reset-password', [
        'email' => 'reset@example.qa',
        'token' => $token,
        'password' => 'New!Pass5678',
        'password_confirmation' => 'New!Pass5678',
    ])->assertOk();

    $afterRefreshActive = RefreshToken::query()
        ->where('user_id', $this->user->id)
        ->whereNull('used_at')
        ->count();
    $afterSanctum = PersonalAccessToken::query()
        ->where('tokenable_id', $this->user->id)
        ->count();

    expect($afterRefreshActive)->toBe(0)
        ->and($afterSanctum)->toBe(0);
});

it('validates that email + token + password are required', function (): void {
    postJson('/api/v1/auth/reset-password', [])
        ->assertStatus(422)
        ->assertJson(
            fn ($json) => $json
                ->where('error.code', 'VALIDATION_FAILED')
                ->has('error.details.email')
                ->has('error.details.token')
                ->has('error.details.password')
                ->etc(),
        );
});
