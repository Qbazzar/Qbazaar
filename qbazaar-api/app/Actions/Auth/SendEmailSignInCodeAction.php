<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\OtpPurpose;
use App\Exceptions\DomainException;
use App\Models\User;
use App\Notifications\EmailSignInCodeNotification;
use App\Services\Auth\OtpIssueResult;
use App\Services\Auth\ThrottledOtpIssuer;
use Illuminate\Support\Facades\Notification;

/**
 * Mails a sign-in code to any address, registered or not, so the response
 * never reveals whether an account exists. Whether the code signs in or
 * signs up is decided only once the code is proven.
 */
class SendEmailSignInCodeAction
{
    public function __construct(
        private readonly ThrottledOtpIssuer $issuer,
    ) {}

    /**
     * @throws DomainException AUTH_006 inside the cooldown or over the hourly ceiling
     */
    public function execute(string $email): OtpIssueResult
    {
        $email = strtolower($email);

        $result = $this->issuer->issue($email, OtpPurpose::EMAIL_SIGN_IN);

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        $notification = new EmailSignInCodeNotification(
            code: $result->rawCode,
            expiresInSeconds: $result->expiresIn,
            language: $user !== null ? $user->language->value : app()->getLocale(),
        );

        if ($user !== null) {
            $user->notify($notification);

            return $result;
        }

        Notification::route('mail', $email)->notify($notification);

        return $result;
    }
}
