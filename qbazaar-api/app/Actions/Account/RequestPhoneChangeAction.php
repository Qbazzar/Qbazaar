<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Actions\Auth\SendOtpAction;
use App\Enums\OtpPurpose;
use App\Models\User;
use App\Services\Account\ReauthCodeService;
use App\Services\Auth\OtpIssueResult;
use Illuminate\Support\Facades\Cache;

/**
 * Starts a phone change: after the step-up code, texts an OTP to the new
 * number and remembers it as the pending number until the OTP is confirmed.
 */
class RequestPhoneChangeAction
{
    public function __construct(
        private readonly ReauthCodeService $reauth,
        private readonly SendOtpAction $sendOtp,
    ) {}

    public function execute(User $user, string $newPhone, string $reauthCode): OtpIssueResult
    {
        $this->reauth->consume($user, $reauthCode);

        $result = $this->sendOtp->execute($newPhone, OtpPurpose::PHONE_CHANGE);

        Cache::put(self::pendingKey($user), $newPhone, $result->expiresIn);

        return $result;
    }

    public static function pendingKey(User $user): string
    {
        return "account:phone-change:{$user->id}";
    }
}
