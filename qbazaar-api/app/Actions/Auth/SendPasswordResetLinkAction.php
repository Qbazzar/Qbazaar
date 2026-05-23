<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Password;

/**
 * Initiates the "forgot password" flow.
 *
 * Anti-enumeration: the calling controller ALWAYS responds 202 — this action
 * silently returns whether or not the email matched a user. We do NOT throw
 * if Password broker reports `INVALID_USER`; that's the whole point. Real
 * failures (mail driver explosion, etc.) bubble up normally.
 */
class SendPasswordResetLinkAction
{
    public function execute(string $email): void
    {
        // Password::sendResetLink stores a token in password_reset_tokens
        // and dispatches User::sendPasswordResetNotification — which we've
        // wired to PasswordResetNotification (localised).
        Password::broker()->sendResetLink(['email' => $email]);
    }
}
