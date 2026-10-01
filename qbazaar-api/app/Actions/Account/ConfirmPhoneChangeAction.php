<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Enums\OtpPurpose;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Applies the pending phone change once the OTP sent to the new number is
 * confirmed; the new number is verified by that same code.
 */
class ConfirmPhoneChangeAction
{
    public function __construct(private readonly OtpService $otp) {}

    public function execute(User $user, string $code): User
    {
        $newPhone = Cache::get(RequestPhoneChangeAction::pendingKey($user));

        if (! is_string($newPhone)) {
            throw new DomainException(ErrorCode::AUTH_OTP_INVALID);
        }

        $this->otp->verify($newPhone, $code, OtpPurpose::PHONE_CHANGE);

        try {
            DB::transaction(function () use ($user, $newPhone): void {
                User::query()->whereKey($user->id)->lockForUpdate()->value('id');

                if (User::query()->withTrashed()->where('phone', $newPhone)->whereKeyNot($user->id)->exists()) {
                    throw new DomainException(ErrorCode::AUTH_PHONE_EXISTS);
                }

                $user->forceFill(['phone' => $newPhone, 'phone_verified' => true])->save();
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException(ErrorCode::AUTH_PHONE_EXISTS);
        }

        Cache::forget(RequestPhoneChangeAction::pendingKey($user));

        return $user;
    }
}
