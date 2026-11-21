<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('returns the same body as /account/profile', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $profile = getJson('/api/v1/account/profile')->assertOk()->json('data');

    getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data', $profile);
});

it('requires authentication', function (): void {
    getJson('/api/v1/me')->assertUnauthorized();
});

it('refuses a suspended user', function (): void {
    Sanctum::actingAs(User::factory()->suspended()->create(), ['*']);

    getJson('/api/v1/me')->assertForbidden();
});

it('says whether the account has a password', function (?string $password, bool $expected): void {
    Sanctum::actingAs(User::factory()->create(['password' => $password]), ['*']);

    getJson('/api/v1/me')->assertOk()->assertJsonPath('data.has_password', $expected);
    getJson('/api/v1/account/profile')->assertOk()->assertJsonPath('data.has_password', $expected);
})->with([
    'password sign-up' => ['Str0ng!Pass1', true],
    'email-code or social sign-up' => [null, false],
]);
