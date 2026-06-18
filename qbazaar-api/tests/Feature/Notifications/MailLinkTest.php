<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\EmailVerificationNotification;
use App\Notifications\PasswordResetNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Regression: transactional mail links must point at the qbazaar-web frontend
 * (config qbazaar.web_url), not localhost / the raw API JSON route. The reset
 * link previously read a nonexistent config('app.frontend_url') and fell back
 * to localhost:3000.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Neutralise any URL callback a prior test may have registered so the real
    // builders run.
    ResetPassword::$createUrlCallback = null;
    VerifyEmail::$createUrlCallback = null;
    config(['qbazaar.web_url' => 'https://web.test']);
});

it('points the password-reset link at the frontend reset page', function (): void {
    $user = User::factory()->create();

    $mail = (new PasswordResetNotification('tok-123'))->toMail($user);

    expect($mail->actionUrl)->toStartWith('https://web.test/reset-password');
});

it('points the email-verification link at the frontend verify page', function (): void {
    $user = User::factory()->create();

    $mail = (new EmailVerificationNotification)->toMail($user);

    expect($mail->actionUrl)->toStartWith('https://web.test/verify-email');
});
