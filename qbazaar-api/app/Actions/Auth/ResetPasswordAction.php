<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Password;

/**
 * Completes the password-reset flow.
 *
 *  - Verifies the token + email via Laravel's Password broker.
 *  - Sets the new password (hashed via User::$casts).
 *  - Burns ALL refresh tokens for the user (forces re-login on every device).
 *  - Burns Sanctum personal access tokens too (same reason).
 *  - Fires Auth\Events\PasswordReset so observers (Wave 3 activity log) can
 *    log it.
 *
 * Throws DomainException(VALIDATION_FAILED) with broker-supplied details on
 * a bad / expired token so the global handler returns 422 with field-level
 * info — matching the contract's ValidationError response.
 */
class ResetPasswordAction
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokens,
    ) {}

    /**
     * @throws DomainException
     */
    public function execute(string $email, string $token, string $password): void
    {
        $status = Password::broker()->reset(
            [
                'email' => $email,
                'token' => $token,
                'password' => $password,
                'password_confirmation' => $password,
            ],
            function (Authenticatable $user, string $plain): void {
                if (! $user instanceof User) {
                    return;
                }

                $user->forceFill([
                    'password' => $plain, // hashed via $casts
                ])->save();

                $this->refreshTokens->burnAllForUser($user);
                $user->tokens()->delete();

                // Pushes must stop when sessions are burned — drop FCM tokens too.
                $user->deviceTokens()->delete();

                Event::dispatch(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new DomainException(
                ErrorCode::VALIDATION_FAILED,
                details: ['token' => [__($status)]],
            );
        }
    }
}
