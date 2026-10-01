<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Services\Auth\DeviceChallengeService;
use App\Services\Auth\OtpService;
use App\Services\Auth\SignInResult;

/**
 * Finishes a sign-in that was held at the new-device check: the SMS code
 * proves the phone, the device becomes trusted and the tokens are issued.
 */
class VerifyNewDeviceAction
{
    public function __construct(
        private readonly DeviceChallengeService $challenges,
        private readonly OtpService $otpService,
        private readonly CompleteSignInAction $completeSignIn,
    ) {}

    /**
     * @throws DomainException
     */
    public function execute(string $challengeToken, string $code): SignInResult
    {
        $pending = $this->challenges->resolve($challengeToken);

        /** @var User|null $user */
        $user = User::query()->find($pending->userId);

        if ($user === null) {
            throw new DomainException(ErrorCode::AUTH_DEVICE_CHALLENGE_INVALID);
        }

        $this->otpService->verify($user->phone, $code, OtpPurpose::NEW_DEVICE);
        $this->challenges->forget($challengeToken);

        // The account may have been suspended while the code was in flight.
        if ($user->status === UserStatus::SUSPENDED) {
            throw new DomainException(ErrorCode::AUTH_ACCOUNT_SUSPENDED);
        }

        if (! $user->phone_verified) {
            $user->forceFill(['phone_verified' => true])->save();
        }

        return SignInResult::authenticated($user, $this->completeSignIn->openSession($user, $pending->device));
    }
}
