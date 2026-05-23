<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

it('registers a new user and returns the auth envelope', function (): void {
    $payload = [
        'full_name' => 'Ahmed Al-Ali',
        'email' => 'ahmed@example.qa',
        'phone' => '+97455123456',
        'password' => 'Str0ng!Pass',
        'account_type' => 'private',
        'language' => 'ar',
        'accepted_terms' => true,
    ];

    $response = postJson('/api/v1/auth/register', $payload);

    $response
        ->assertCreated()
        ->assertJson(
            fn ($json) => $json
                ->where('success', true)
                ->where('data.user.full_name', 'Ahmed Al-Ali')
                ->where('data.user.email', 'ahmed@example.qa')
                ->where('data.user.phone', '+97455123456')
                ->where('data.user.account_type', 'private')
                ->where('data.user.status', 'active')
                ->where('data.user.email_verified', false)
                ->where('data.user.phone_verified', false)
                ->where('data.user.language', 'ar')
                ->has('data.user.id')
                ->has('data.user.created_at')
                ->has('data.tokens.access_token')
                ->has('data.tokens.refresh_token')
                ->where('data.tokens.token_type', 'Bearer')
                ->has('data.tokens.expires_in')
                ->etc(),
        );

    expect(User::query()->where('email', 'ahmed@example.qa')->exists())->toBeTrue();
});

it('rejects invalid phone formats', function (): void {
    $response = postJson('/api/v1/auth/register', [
        'full_name' => 'Bad Phone',
        'email' => 'bad@example.qa',
        'phone' => '0501234567', // missing +974
        'password' => 'Str0ng!Pass',
        'account_type' => 'private',
        'accepted_terms' => true,
    ]);

    $response
        ->assertStatus(422)
        ->assertJson(
            fn ($json) => $json
                ->where('success', false)
                ->where('error.code', 'VALIDATION_FAILED')
                ->has('error.details.phone')
                ->etc(),
        );
});

it('rejects duplicate email with AUTH-style validation', function (): void {
    User::factory()->create(['email' => 'taken@example.qa']);

    $response = postJson('/api/v1/auth/register', [
        'full_name' => 'Dup Email',
        'email' => 'taken@example.qa',
        'phone' => '+97455999888',
        'password' => 'Str0ng!Pass',
        'account_type' => 'private',
        'accepted_terms' => true,
    ]);

    $response
        ->assertStatus(422)
        ->assertJson(
            fn ($json) => $json
                ->where('success', false)
                ->where('error.code', 'VALIDATION_FAILED')
                ->has('error.details.email')
                ->etc(),
        );
});

it('rejects weak passwords missing symbol or digit', function (): void {
    $response = postJson('/api/v1/auth/register', [
        'full_name' => 'Weak Pass',
        'email' => 'weak@example.qa',
        'phone' => '+97455111222',
        'password' => 'weakpass', // no upper, no digit, no symbol
        'account_type' => 'private',
        'accepted_terms' => true,
    ]);

    $response
        ->assertStatus(422)
        ->assertJson(
            fn ($json) => $json
                ->where('error.code', 'VALIDATION_FAILED')
                ->has('error.details.password')
                ->etc(),
        );
});

it('requires accepted_terms to be true', function (): void {
    $response = postJson('/api/v1/auth/register', [
        'full_name' => 'No Terms',
        'email' => 'noterms@example.qa',
        'phone' => '+97455333444',
        'password' => 'Str0ng!Pass',
        'account_type' => 'private',
        'accepted_terms' => false,
    ]);

    $response
        ->assertStatus(422)
        ->assertJson(
            fn ($json) => $json
                ->where('error.code', 'VALIDATION_FAILED')
                ->has('error.details.accepted_terms')
                ->etc(),
        );
});
