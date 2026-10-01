<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\OtpPurpose;
use App\Exceptions\DomainException;
use App\Models\User;
use App\Notifications\Channels\TwilioSmsChannel;
use App\Notifications\OtpNotification;
use App\Services\Auth\OtpIssueResult;
use App\Services\Auth\ThrottledOtpIssuer;
use Illuminate\Support\Facades\Notification;

/**
 * Issues a phone-verification code and sends it by SMS. /send-otp and
 * /resend-otp share the same per-phone cooldown and hourly ceiling, so one
 * cannot be used to get around the other.
 */
class SendOtpAction
{
    public function __construct(
        private readonly ThrottledOtpIssuer $issuer,
    ) {}

    /**
     * @throws DomainException
     */
    public function execute(string $phone, OtpPurpose $purpose = OtpPurpose::PHONE_VERIFICATION): OtpIssueResult
    {
        $result = $this->issuer->issue($phone, $purpose);

        // A registered owner only changes the SMS locale; delivery always goes
        // to the phone number itself.
        /** @var User|null $user */
        $user = User::query()->where('phone', $phone)->first();

        $notification = new OtpNotification(
            phone: $phone,
            code: $result->rawCode,
            expiresInSeconds: $result->expiresIn,
        );

        if ($user !== null) {
            $user->notify($notification);

            return $result;
        }

        Notification::route(TwilioSmsChannel::class, $phone)->notify($notification);

        return $result;
    }
}
