<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    config(['qbazaar.auth.password_login_enabled' => false]);
});

it('refuses password sign-in when it is turned off', function (): void {
    User::factory()->create(['email' => 'owner@example.qa', 'password' => Hash::make('Str0ng!Pass')]);

    postJson('/api/v1/auth/login', ['identifier' => 'owner@example.qa', 'password' => 'Str0ng!Pass'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'AUTH_014');
});

it('refuses password sign-up when it is turned off', function (): void {
    postJson('/api/v1/auth/register', [
        'full_name' => 'Ahmed Al-Ali',
        'email' => 'ahmed@example.qa',
        'phone' => '+97455123456',
        'password' => 'Str0ng!Pass',
        'account_type' => 'private',
        'accepted_terms' => true,
    ])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'AUTH_014');

    expect(User::query()->count())->toBe(0);
});

it('answers AUTH_001 for an account that has no password', function (): void {
    config(['qbazaar.auth.password_login_enabled' => true]);
    User::factory()->create(['email' => 'owner@example.qa', 'password' => null]);

    postJson('/api/v1/auth/login', ['identifier' => 'owner@example.qa', 'password' => 'anything'])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'AUTH_001');
});
