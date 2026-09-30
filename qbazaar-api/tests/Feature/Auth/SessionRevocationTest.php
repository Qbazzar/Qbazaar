<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use App\Services\Auth\TokenPair;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    $this->user = User::factory()->create();
});

function issueSession(User $user): TokenPair
{
    return app(RefreshTokenService::class)->issue($user);
}

function accessTokenOf(TokenPair $pair): PersonalAccessToken
{
    /** @var PersonalAccessToken */
    return PersonalAccessToken::findToken($pair->accessToken);
}

function expectRefreshRejected(TokenPair $pair, int $status = 401, string $code = 'AUTH_010'): void
{
    postJson('/api/v1/auth/refresh', ['refresh_token' => $pair->refreshToken])
        ->assertStatus($status)
        ->assertJsonPath('error.code', $code);
}

it('links each refresh token to the access token it was issued with', function (): void {
    $pair = issueSession($this->user);

    expect(RefreshToken::query()->sole()->personal_access_token_id)
        ->toBe(accessTokenOf($pair)->getKey());
});

it('revoking a session also burns its refresh token', function (): void {
    $current = issueSession($this->user);
    $other = issueSession($this->user);
    $otherAccessToken = accessTokenOf($other);

    deleteJson(
        "/api/v1/account/sessions/{$otherAccessToken->getKey()}",
        [],
        ['Authorization' => 'Bearer ' . $current->accessToken],
    )->assertNoContent();

    expect(PersonalAccessToken::query()->whereKey($otherAccessToken->getKey())->exists())->toBeFalse();
    expectRefreshRejected($other);

    postJson('/api/v1/auth/refresh', ['refresh_token' => $current->refreshToken])->assertOk();
});

it('logging out burns the refresh token of the current session', function (): void {
    $pair = issueSession($this->user);

    postJson('/api/v1/auth/logout', [], ['Authorization' => 'Bearer ' . $pair->accessToken])
        ->assertNoContent();

    expectRefreshRejected($pair);
});

it('keeps an idle session listed while its refresh token is still valid', function (): void {
    $idle = issueSession($this->user);
    accessTokenOf($idle)->forceFill(['expires_at' => Carbon::now()->subHour()])->save();
    $current = issueSession($this->user);

    $response = getJson('/api/v1/account/sessions', ['Authorization' => 'Bearer ' . $current->accessToken])
        ->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())
        ->toContain((string) accessTokenOf($idle)->getKey());
});

it('refuses to rotate a refresh token once the user is suspended', function (): void {
    $pair = issueSession($this->user);
    $this->user->forceFill(['status' => UserStatus::SUSPENDED])->save();

    expectRefreshRejected($pair, 403, 'AUTH_002');

    expect(RefreshToken::query()->count())->toBe(1);
});

it('burns every token when an admin suspends the user', function (): void {
    $pair = issueSession($this->user);
    issueSession($this->user);

    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    actingAs($admin)
        ->post("/admin/users/{$this->user->id}/suspend")
        ->assertRedirect();

    expect($this->user->fresh()->status)->toBe(UserStatus::SUSPENDED)
        ->and($this->user->tokens()->count())->toBe(0)
        ->and(RefreshToken::query()->where('user_id', $this->user->id)->whereNull('used_at')->count())->toBe(0);

    $this->user->forceFill(['status' => UserStatus::ACTIVE])->save();
    expectRefreshRejected($pair);
});
