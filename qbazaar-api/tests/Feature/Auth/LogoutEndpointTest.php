<?php

declare(strict_types=1);

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

it('revokes the current access token and returns 204', function (): void {
    $user = User::factory()->create();
    $accessToken = $user->createToken('test')->plainTextToken;

    postJson(
        '/api/v1/auth/logout',
        [],
        ['Authorization' => 'Bearer ' . $accessToken],
    )->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});

it('returns 401 when no bearer is presented', function (): void {
    postJson('/api/v1/auth/logout', [])
        ->assertStatus(401)
        ->assertJson(
            fn ($json) => $json
                ->where('success', false)
                ->where('error.code', 'AUTH_010')
                ->etc(),
        );
});

it('also invalidates a supplied refresh token', function (): void {
    $user = User::factory()->create();
    $accessToken = $user->createToken('test')->plainTextToken;

    /** @var RefreshTokenService $service */
    $service = app(RefreshTokenService::class);
    $tokens = $service->issue($user);

    postJson(
        '/api/v1/auth/logout',
        ['refresh_token' => $tokens->refreshToken],
        ['Authorization' => 'Bearer ' . $accessToken],
    )->assertNoContent();

    expect(RefreshToken::query()->where('user_id', $user->id)->exists())->toBeFalse();

    postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens->refreshToken])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'AUTH_010');
});
