<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\EmailVerificationNotification;
use App\Notifications\PasswordResetNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;
use function Pest\Laravel\withServerVariables;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    Queue::fake();
    Mail::fake();
});

it('queues the reset mail on the notifications queue instead of sending it in the request', function (): void {
    User::factory()->create(['email' => 'known@example.qa']);

    postJson('/api/v1/auth/forgot-password', ['email' => 'known@example.qa'])->assertStatus(202);

    Queue::assertPushedOn('notifications', SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof PasswordResetNotification);
    Mail::assertNothingSent();
});

it('queues the verification mail on the notifications queue', function (): void {
    Sanctum::actingAs(User::factory()->create(['email_verified' => false]), ['*']);

    postJson('/api/v1/auth/send-email-verification')->assertStatus(202);

    Queue::assertPushedOn('notifications', SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof EmailVerificationNotification);
});

it('retries both mails instead of dropping them on a mail outage', function (string $class): void {
    expect(is_subclass_of($class, ShouldQueue::class))->toBeTrue();

    $defaults = (new ReflectionClass($class))->getDefaultProperties();
    expect($defaults['tries'])->toBeGreaterThan(1)
        ->and($defaults['backoff'])->not->toBeEmpty();
})->with([PasswordResetNotification::class, EmailVerificationNotification::class]);

it('caps reset links per inbox whatever address asks', function (): void {
    config(['qbazaar.auth.rate_limits.email_links_per_hour' => 2]);
    User::factory()->create(['email' => 'victim@example.qa']);

    foreach (['10.0.0.1', '10.0.0.2'] as $ip) {
        withServerVariables(['REMOTE_ADDR' => $ip]);
        postJson('/api/v1/auth/forgot-password', ['email' => 'victim@example.qa'])->assertStatus(202);
    }

    withServerVariables(['REMOTE_ADDR' => '10.0.0.3']);
    postJson('/api/v1/auth/forgot-password', ['email' => 'Victim@Example.qa'])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');

    postJson('/api/v1/auth/forgot-password', ['email' => 'someone-else@example.qa'])->assertStatus(202);
});

it('caps verification mails per account', function (): void {
    config(['qbazaar.auth.rate_limits.email_links_per_hour' => 1]);
    Sanctum::actingAs(User::factory()->create(['email_verified' => false]), ['*']);

    postJson('/api/v1/auth/send-email-verification')->assertStatus(202);
    postJson('/api/v1/auth/send-email-verification')->assertStatus(429);
});

it('does not let requests without an address share one global bucket', function (): void {
    config(['qbazaar.auth.rate_limits.email_links_per_hour' => 1]);

    withServerVariables(['REMOTE_ADDR' => '10.0.0.1']);
    postJson('/api/v1/auth/forgot-password', [])->assertStatus(422);
    postJson('/api/v1/auth/forgot-password', [])->assertStatus(429);

    withServerVariables(['REMOTE_ADDR' => '10.0.0.2']);
    postJson('/api/v1/auth/forgot-password', [])->assertStatus(422);
});
