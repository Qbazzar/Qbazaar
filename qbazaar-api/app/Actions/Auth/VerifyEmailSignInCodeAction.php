<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\OtpPurpose;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Services\Auth\DeviceContext;
use App\Services\Auth\OtpService;
use App\Services\Auth\SignInResult;

/**
 * Proves the email code, then signs the owner in, or signs them up when no
 * account uses the address yet.
 *
 * An unknown address without sign-up details gets AUTH_011 only after the
 * code checks out, and the code is left unspent so the client can come back
 * with the details and the same code.
 */
class VerifyEmailSignInCodeAction
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly RegisterUserAction $registerUser,
        private readonly CompleteSignInAction $completeSignIn,
    ) {}

    /**
     * @param array{full_name: string, phone: string, account_type: string, language?: string}|null $registration
     *
     * @throws DomainException
     */
    public function execute(string $email, string $code, ?array $registration, DeviceContext $device): SignInResult
    {
        $email = strtolower($email);

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null && $registration === null) {
            $this->otpService->verify($email, $code, OtpPurpose::EMAIL_SIGN_IN, consume: false);

            throw new DomainException(ErrorCode::AUTH_REGISTRATION_REQUIRED, details: ['email' => $email]);
        }

        $this->otpService->verify($email, $code, OtpPurpose::EMAIL_SIGN_IN);

        if ($user === null) {
            $created = $this->registerUser->execute([...$registration, 'email' => $email], $device, emailVerified: true);

            return SignInResult::registered($created['user'], $created['tokens']);
        }

        if (! $user->email_verified) {
            $user->forceFill(['email_verified' => true])->save();
        }

        return $this->completeSignIn->execute($user, $device);
    }
}
