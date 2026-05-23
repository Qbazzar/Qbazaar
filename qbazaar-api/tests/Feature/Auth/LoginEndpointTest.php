<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    RateLimiter::clear('auth|127.0.0.1');

    $this->user = User::factory()->create([
        'email' => 'login@example.qa',
        'phone' => '+97455222333',
        'password' => Hash::make('Str0ng!Pass'),
    ]);
});

it('logs in by email and returns the auth envelope', function (): void {
    $response = postJson('/api/v1/auth/login', [
        'identifier' => 'login@example.qa',
        'password' => 'Str0ng!Pass',
    ]);

    $response
        ->assertOk()
        ->assertJson(
            fn ($json) => $json
                ->where('success', true)
                ->where('data.user.email', 'login@example.qa')
                ->has('data.tokens.access_token')
                ->has('data.tokens.refresh_token')
                ->where('data.tokens.token_type', 'Bearer')
                ->etc(),
        );

    expect($this->user->fresh()->last_login_at)->not->toBeNull();
});

it('logs in by phone too', function (): void {
    $response = postJson('/api/v1/auth/login', [
        'identifier' => '+97455222333',
        'password' => 'Str0ng!Pass',
    ]);

    $response
        ->assertOk()
        ->assertJson(
            fn ($json) => $json
                ->where('data.user.phone', '+97455222333')
                ->etc(),
        );
});

it('returns AUTH_001 on bad password', function (): void {
    $response = postJson('/api/v1/auth/login', [
        'identifier' => 'login@example.qa',
        'password' => 'wrong-password',
    ]);

    $response
        ->assertStatus(401)
        ->assertJson(
            fn ($json) => $json
                ->where('success', false)
                ->where('error.code', 'AUTH_001')
                ->etc(),
        );
});

it('returns AUTH_001 on unknown identifier', function (): void {
    postJson('/api/v1/auth/login', [
        'identifier' => 'nobody@example.qa',
        'password' => 'whatever',
    ])
        ->assertStatus(401)
        ->assertJson(
            fn ($json) => $json
                ->where('error.code', 'AUTH_001')
                ->etc(),
        );
});

it('returns AUTH_002 on suspended account', function (): void {
    $this->user->forceFill(['status' => UserStatus::SUSPENDED->value])->save();

    postJson('/api/v1/auth/login', [
        'identifier' => 'login@example.qa',
        'password' => 'Str0ng!Pass',
    ])
        ->assertStatus(403)
        ->assertJson(
            fn ($json) => $json
                ->where('error.code', 'AUTH_002')
                ->etc(),
        );
});

it('validates that identifier and password are required', function (): void {
    postJson('/api/v1/auth/login', [])
        ->assertStatus(422)
        ->assertJson(
            fn ($json) => $json
                ->where('error.code', 'VALIDATION_FAILED')
                ->has('error.details.identifier')
                ->has('error.details.password')
                ->etc(),
        );
});
