<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Notifications\Channels\TwilioSmsChannel;
use App\Notifications\OtpNotification;
use App\Services\Auth\OtpIssueResult;
use App\Services\Auth\OtpService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Issues a fresh OTP for a phone and dispatches the notification.
 *
 * Throttling decisions (in priority order):
 *  1. Per-phone "resend cooldown" lock — 60s by default. Prevents a tight
 *     loop from spamming Twilio even if a user keeps re-tapping send. This is
 *     the soft client-facing limit; trying again inside the window throws
 *     AUTH_006.
 *  2. Per-phone hourly ceiling — 5 sends/hr by default. Hard backstop against
 *     a malicious script that loops on a single phone. Same AUTH_006 code.
 *
 * If $resendOnly is true (i.e. caller is /resend-otp), the cooldown is
 * checked BEFORE issuing. /send-otp also respects the cooldown — so an
 * attacker can't bypass it by calling send-otp instead of resend-otp.
 */
class SendOtpAction
{
    public function __construct(
        private readonly OtpService $otpService,
    ) {}

    /**
     * @throws DomainException
     */
    public function execute(string $phone): OtpIssueResult
    {
        $this->enforceCooldown($phone);
        $this->enforceHourlyCeiling($phone);

        $result = $this->otpService->issue($phone);

        $this->markCooldown($phone, $result->canResendIn);

        $this->dispatchNotification($phone, $result);

        return $result;
    }

    /**
     * @throws DomainException
     */
    private function enforceCooldown(string $phone): void
    {
        if (Cache::has($this->cooldownKey($phone))) {
            throw new DomainException(ErrorCode::AUTH_RATE_LIMITED);
        }
    }

    /**
     * @throws DomainException
     */
    private function enforceHourlyCeiling(string $phone): void
    {
        $max = (int) config('qbazaar.otp.max_per_hour', 5);

        if ($this->otpService->countLastHour($phone) >= $max) {
            throw new DomainException(ErrorCode::AUTH_RATE_LIMITED);
        }
    }

    private function markCooldown(string $phone, int $seconds): void
    {
        Cache::put($this->cooldownKey($phone), true, $seconds);
    }

    private function cooldownKey(string $phone): string
    {
        return 'otp:cooldown:' . $phone;
    }

    private function dispatchNotification(string $phone, OtpIssueResult $result): void
    {
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

            return;
        }

        Notification::route(TwilioSmsChannel::class, $phone)
            ->notify($notification);
    }
}
