<?php

declare(strict_types=1);

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    $this->user = User::factory()->create();
});

function rowForRawToken(string $raw): RefreshToken
{
    return RefreshToken::query()->findOrFail(strtolower(substr($raw, 3, 26)));
}

it('stores new refresh tokens as an HMAC-SHA256 digest, not bcrypt', function (): void {
    $pair = app(RefreshTokenService::class)->issue($this->user);

    expect(rowForRawToken($pair->refreshToken)->token_hash)
        ->toMatch('/^[0-9a-f]{64}$/')
        ->not->toContain($pair->refreshToken);
});

it('rotates a token without running bcrypt', function (): void {
    $pair = app(RefreshTokenService::class)->issue($this->user);
    Hash::partialMock()->shouldNotReceive('check');
    Hash::partialMock()->shouldNotReceive('make');

    postJson('/api/v1/auth/refresh', ['refresh_token' => $pair->refreshToken])->assertOk();
});

it('accepts a legacy bcrypt token once and replaces it with an HMAC token', function (): void {
    $rowId = strtolower((string) Str::ulid());
    $raw = 'rt_' . $rowId . Str::random(16);
    $legacy = new RefreshToken;
    $legacy->id = $rowId;
    $legacy->forceFill([
        'user_id' => $this->user->id,
        'token_hash' => Hash::make($raw),
        'expires_at' => Carbon::now()->addDay(),
    ])->save();

    $newRaw = postJson('/api/v1/auth/refresh', ['refresh_token' => $raw])
        ->assertOk()
        ->json('data.tokens.refresh_token');

    expect($legacy->fresh()?->used_at)->not->toBeNull()
        ->and(rowForRawToken($newRaw)->token_hash)->toMatch('/^[0-9a-f]{64}$/');
});

it('rejects a token whose digest does not match', function (): void {
    $pair = app(RefreshTokenService::class)->issue($this->user);
    $tampered = substr($pair->refreshToken, 0, -1) . (str_ends_with($pair->refreshToken, 'a') ? 'b' : 'a');

    postJson('/api/v1/auth/refresh', ['refresh_token' => $tampered])
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'AUTH_010');
});

it('keeps tokens valid across an APP_KEY rotation listed in previous_keys', function (): void {
    $pair = app(RefreshTokenService::class)->issue($this->user);
    $oldKey = config('app.key');

    config(['app.key' => 'base64:' . base64_encode(random_bytes(32)), 'app.previous_keys' => [$oldKey]]);

    postJson('/api/v1/auth/refresh', ['refresh_token' => $pair->refreshToken])->assertOk();
});

it('rejects tokens minted under a key that is no longer listed', function (): void {
    $pair = app(RefreshTokenService::class)->issue($this->user);

    config(['app.key' => 'base64:' . base64_encode(random_bytes(32)), 'app.previous_keys' => []]);

    postJson('/api/v1/auth/refresh', ['refresh_token' => $pair->refreshToken])->assertStatus(401);
});
