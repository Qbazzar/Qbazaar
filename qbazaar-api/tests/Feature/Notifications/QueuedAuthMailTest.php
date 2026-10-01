<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\EmailVerificationNotification;
use App\Notifications\PasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    RateLimiter::clear('auth|127.0.0.1');
});

it('queues the password-reset mail instead of sending it inside the request', function (): void {
    Queue::fake();
    Mail::fake();
    User::factory()->create(['email' => 'known@example.qa']);

    postJson('/api/v1/auth/forgot-password', ['email' => 'known@example.qa'])->assertStatus(202);

    Queue::assertPushed(
        SendQueuedNotifications::class,
        fn (SendQueuedNotifications $job): bool => $job->notification instanceof PasswordResetNotification,
    );
    Mail::assertNothingSent();
});

it('queues the email-verification mail', function (): void {
    Queue::fake();
    $user = User::factory()->create(['email_verified' => false]);

    $user->sendEmailVerificationNotification();

    Queue::assertPushed(
        SendQueuedNotifications::class,
        fn (SendQueuedNotifications $job): bool => $job->notification instanceof EmailVerificationNotification,
    );
});

it('retries the auth mails when the mail server hiccups', function (string $notification): void {
    $instance = $notification === PasswordResetNotification::class
        ? new PasswordResetNotification('token')
        : new EmailVerificationNotification;

    expect($instance->tries)->toBe(3)
        ->and($instance->backoff)->toBe([10, 60, 300]);
})->with([PasswordResetNotification::class, EmailVerificationNotification::class]);
