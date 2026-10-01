<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\OtpPurpose;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Notifications\OtpNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Holds a sign-in from an unknown device until the account's phone proves it
 * with an SMS code. The pending sign-in lives in the cache under the hash of
 * an opaque token, so a leaked cache dump cannot be replayed as a token.
 */
class DeviceChallengeService
{
    public function __construct(
        private readonly ThrottledOtpIssuer $issuer,
    ) {}

    /**
     * @throws DomainException AUTH_006 when the phone is inside its SMS cooldown
     */
    public function start(User $user, DeviceContext $device): DeviceChallenge
    {
        $otp = $this->issuer->issue($user->phone, OtpPurpose::NEW_DEVICE);

        $user->notify(new OtpNotification(
            phone: $user->phone,
            code: $otp->rawCode,
            expiresInSeconds: $otp->expiresIn,
        ));

        $token = Str::random(64);
        $ttlSeconds = (int) config('qbazaar.auth.new_device_check.challenge_ttl_minutes') * 60;

        Cache::put($this->key($token), [
            'user_id' => $user->id,
            'device_hash' => $device->hash,
            'device_label' => $device->label,
            'ip' => $device->ip,
        ], $ttlSeconds);

        return new DeviceChallenge(
            token: $token,
            maskedPhone: $this->mask($user->phone),
            expiresIn: min($otp->expiresIn, $ttlSeconds),
            canResendIn: $otp->canResendIn,
        );
    }

    /**
     * @throws DomainException AUTH_012 when the token is unknown or expired
     */
    public function resolve(string $token): PendingDeviceChallenge
    {
        $pending = Cache::get($this->key($token));

        if (! is_array($pending)) {
            throw new DomainException(ErrorCode::AUTH_DEVICE_CHALLENGE_INVALID);
        }

        return new PendingDeviceChallenge(
            userId: (string) $pending['user_id'],
            device: new DeviceContext(
                hash: (string) $pending['device_hash'],
                label: (string) $pending['device_label'],
                ip: (string) $pending['ip'],
            ),
        );
    }

    public function forget(string $token): void
    {
        Cache::forget($this->key($token));
    }

    private function key(string $token): string
    {
        return 'auth:device-challenge:' . hash('sha256', $token);
    }

    private function mask(string $phone): string
    {
        return substr($phone, 0, 4) . str_repeat('*', max(0, strlen($phone) - 7)) . substr($phone, -3);
    }
}
