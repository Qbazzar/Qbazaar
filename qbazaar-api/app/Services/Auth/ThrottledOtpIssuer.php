<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\OtpPurpose;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use Illuminate\Support\Facades\Cache;

/**
 * Issues a code only when the recipient is outside its resend cooldown and
 * under the hourly ceiling. Every send costs an SMS or an email, so each
 * recipient gets these limits whatever IP or endpoint the request comes from;
 * the route throttles cover the per-IP side.
 */
class ThrottledOtpIssuer
{
    public function __construct(
        private readonly OtpService $otpService,
    ) {}

    /**
     * @throws DomainException AUTH_006 when the recipient is throttled
     */
    public function issue(string $recipient, OtpPurpose $purpose): OtpIssueResult
    {
        $cooldownKey = 'otp:cooldown:' . $purpose->value . ':' . $recipient;

        if (Cache::has($cooldownKey)) {
            throw new DomainException(ErrorCode::AUTH_RATE_LIMITED);
        }

        if ($this->otpService->countLastHour($recipient, $purpose) >= (int) config('qbazaar.otp.max_per_hour', 5)) {
            throw new DomainException(ErrorCode::AUTH_RATE_LIMITED);
        }

        $result = $this->otpService->issue($recipient, $purpose);

        Cache::put($cooldownKey, true, $result->canResendIn);

        return $result;
    }
}
