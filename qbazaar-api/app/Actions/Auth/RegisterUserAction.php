<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\AccountType;
use App\Enums\Language;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use App\Services\Auth\RefreshTokenService;
use App\Services\Auth\TokenPair;
use Illuminate\Support\Facades\DB;

/**
 * Creates a brand-new user + issues the initial access/refresh token pair.
 *
 * Wrapped in a DB transaction so a failure halfway through (e.g. token table
 * write) doesn't leave an orphaned half-created user.
 *
 * Side effects (post-commit):
 *  - WelcomeNotification — fired after the transaction commits via the
 *    notification's queue/mailer; even if it fails, the user row + tokens are
 *    already durable, so register stays idempotent from the client's view.
 */
class RegisterUserAction
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokens,
    ) {}

    /**
     * @param  array{
     *     full_name: string,
     *     email: string,
     *     phone: string,
     *     password: string,
     *     account_type: string,
     *     language?: string,
     * }  $data
     * @return array{user: User, tokens: TokenPair}
     */
    public function execute(array $data, ?string $deviceFingerprint = null, ?string $ip = null, ?string $deviceLabel = null): array
    {
        /** @var array{user: User, tokens: TokenPair} $result */
        $result = DB::transaction(function () use ($data, $deviceFingerprint, $ip, $deviceLabel) {
            $user = User::query()->create([
                'full_name' => $data['full_name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'],
                'password' => $data['password'], // hashed via $casts
                'account_type' => $data['account_type'] ?? AccountType::PRIVATE_INDIVIDUAL->value,
                'status' => UserStatus::ACTIVE->value,
                'email_verified' => false,
                'phone_verified' => false,
                'language' => $data['language'] ?? Language::ARABIC->value,
            ]);

            $tokens = $this->refreshTokens->issue($user, $deviceFingerprint, $ip, $deviceLabel);

            return ['user' => $user, 'tokens' => $tokens];
        });

        // Dispatched outside the DB transaction so a flaky mail driver can't
        // roll back the user creation.
        $result['user']->notify(new WelcomeNotification);

        return $result;
    }
}
