<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    RateLimiter::clear('auth|127.0.0.1');
    Notification::fake();
});

it('returns 202 + dispatches exactly one notification for a known email', function (): void {
    $user = User::factory()->create(['email' => 'known@example.qa']);

    postJson('/api/v1/auth/forgot-password', ['email' => 'known@example.qa'])
        ->assertStatus(202)
        ->assertJson(
            fn ($json) => $json
                ->where('success', true)
                ->where('data.message_key', 'messages.auth.reset_link_sent')
                ->etc(),
        );

    Notification::assertSentTo($user, PasswordResetNotification::class);
    Notification::assertSentToTimes($user, PasswordResetNotification::class, 1);
});

it('returns 202 for an unknown email and dispatches no notification (anti-enumeration)', function (): void {
    postJson('/api/v1/auth/forgot-password', ['email' => 'ghost@example.qa'])
        ->assertStatus(202)
        ->assertJson(
            fn ($json) => $json
                ->where('success', true)
                ->where('data.message_key', 'messages.auth.reset_link_sent')
                ->etc(),
        );

    Notification::assertNothingSent();
});

it('does not leak the existence of one email by sending to another user', function (): void {
    $alice = User::factory()->create(['email' => 'alice@example.qa']);
    $bob = User::factory()->create(['email' => 'bob@example.qa']);

    postJson('/api/v1/auth/forgot-password', ['email' => 'alice@example.qa'])
        ->assertStatus(202);

    Notification::assertSentTo($alice, PasswordResetNotification::class);
    Notification::assertNotSentTo($bob, PasswordResetNotification::class);
});

it('rejects a malformed email payload with VALIDATION_FAILED', function (): void {
    postJson('/api/v1/auth/forgot-password', ['email' => 'not-an-email'])
        ->assertStatus(422)
        ->assertJson(
            fn ($json) => $json
                ->where('success', false)
                ->where('error.code', 'VALIDATION_FAILED')
                ->has('error.details.email')
                ->etc(),
        );

    Notification::assertNothingSent();
});
