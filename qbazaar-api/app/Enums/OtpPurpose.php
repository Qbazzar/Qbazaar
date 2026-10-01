<?php

declare(strict_types=1);

namespace App\Enums;

enum OtpPurpose: string
{
    case PHONE_VERIFICATION = 'phone_verification';
    case EMAIL_SIGN_IN = 'email_sign_in';
    case NEW_DEVICE = 'new_device';

    public function ttlMinutes(): int
    {
        return (int) config('qbazaar.otp.purposes.' . $this->value . '.ttl_minutes', config('qbazaar.otp.ttl_minutes'));
    }

    public function maxAttempts(): int
    {
        return (int) config('qbazaar.otp.purposes.' . $this->value . '.max_attempts', config('qbazaar.otp.max_attempts'));
    }
}
