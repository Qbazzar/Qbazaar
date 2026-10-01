<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\AccountType;
use App\Enums\Language;
use App\Enums\UserStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use App\Services\Auth\DeviceContext;
use App\Services\Auth\RefreshTokenService;
use App\Services\Auth\TokenPair;
use App\Services\Auth\TrustedDeviceService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Creates a user, trusts the device they signed up from and issues the first
 * token pair. Used by password registration, the email-code flow and
 * Google / Apple sign-up; the latter two prove the email and set no password.
 *
 * The welcome notification goes out after the commit so a flaky mail driver
 * cannot roll back the sign-up.
 */
class RegisterUserAction
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokens,
        private readonly TrustedDeviceService $trustedDevices,
    ) {}

    /**
     * @param  array{
     *     full_name: string,
     *     email: string,
     *     phone: string,
     *     password?: string|null,
     *     account_type?: string,
     *     language?: string,
     * }  $data
     * @return array{user: User, tokens: TokenPair}
     *
     * @throws DomainException AUTH_007 / AUTH_008 when a concurrent sign-up took the email or phone first
     */
    public function execute(array $data, DeviceContext $device, bool $emailVerified = false): array
    {
        $email = strtolower($data['email']);

        try {
            /** @var array{user: User, tokens: TokenPair} $result */
            $result = DB::transaction(function () use ($data, $email, $device, $emailVerified): array {
                $user = User::query()->forceCreate([
                    'full_name' => $data['full_name'],
                    'email' => $email,
                    'phone' => $data['phone'],
                    'password' => $data['password'] ?? null, // hashed via $casts
                    'account_type' => $data['account_type'] ?? AccountType::PRIVATE_INDIVIDUAL->value,
                    'status' => UserStatus::ACTIVE->value,
                    'email_verified' => $emailVerified,
                    'phone_verified' => false,
                    'language' => $data['language'] ?? Language::ARABIC->value,
                ]);

                $this->trustedDevices->trust($user, $device);

                $tokens = $this->refreshTokens->issue($user, $device->hash, $device->ip, $device->label);

                return ['user' => $user, 'tokens' => $tokens];
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException(User::query()->where('email', $email)->exists()
                ? ErrorCode::AUTH_EMAIL_EXISTS
                : ErrorCode::AUTH_PHONE_EXISTS);
        }

        $result['user']->notify(new WelcomeNotification);

        return $result;
    }
}
