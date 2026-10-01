<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\UserStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Notifications\SecurityAlertNotification;
use App\Services\Auth\DeviceChallengeService;
use App\Services\Auth\DeviceContext;
use App\Services\Auth\RefreshTokenService;
use App\Services\Auth\SignInResult;
use App\Services\Auth\TokenPair;
use App\Services\Auth\TrustedDeviceService;
use Illuminate\Support\Carbon;

/**
 * The last step of every sign-in method (password, email code, Google,
 * Apple) once the user has proven who they are: refuse suspended accounts,
 * hold unknown devices behind an SMS challenge, and otherwise mint tokens.
 */
class CompleteSignInAction
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokens,
        private readonly TrustedDeviceService $trustedDevices,
        private readonly DeviceChallengeService $challenges,
    ) {}

    /**
     * @throws DomainException
     */
    public function execute(User $user, DeviceContext $device): SignInResult
    {
        if ($user->status === UserStatus::SUSPENDED) {
            throw new DomainException(ErrorCode::AUTH_ACCOUNT_SUSPENDED);
        }

        if (config('qbazaar.auth.new_device_check.enabled') && ! $this->trustedDevices->isTrusted($user, $device)) {
            return SignInResult::challenged($user, $this->challenges->start($user, $device));
        }

        return SignInResult::authenticated($user, $this->openSession($user, $device));
    }

    /**
     * Mints the token pair for a device that is already proven.
     */
    public function openSession(User $user, DeviceContext $device): TokenPair
    {
        $hasSignedInBefore = $user->last_login_at !== null;

        // A user who deactivated their account, or asked us to delete it but
        // hasn't been wiped yet, is put back to ACTIVE by signing in. The
        // queued DeleteAccountJob re-checks the status before doing anything.
        $user->forceFill([
            'status' => UserStatus::ACTIVE,
            'deletion_requested_at' => null,
            'last_login_at' => Carbon::now(),
        ])->save();

        $isNewDevice = $this->trustedDevices->trust($user, $device);

        $tokens = $this->refreshTokens->issue($user, $device->hash, $device->ip, $device->label);

        if ($isNewDevice && $hasSignedInBefore) {
            $user->notify(new SecurityAlertNotification(
                deviceLabel: $device->label,
                ip: $device->ip,
                occurredAt: Carbon::now(),
            ));
        }

        return $tokens;
    }
}
