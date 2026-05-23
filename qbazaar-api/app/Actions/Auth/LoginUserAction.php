<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\UserStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use App\Services\Auth\TokenPair;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Authenticates a user by either email OR Qatari phone, then mints a fresh
 * access+refresh pair. Why not Auth::attempt()? Because we have a single
 * `identifier` field on the wire and Hash::check + manual lookup is cleaner
 * than building dynamic credentials arrays.
 */
class LoginUserAction
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokens,
    ) {}

    /**
     * @return array{user: User, tokens: TokenPair}
     *
     * @throws DomainException
     */
    public function execute(string $identifier, string $password, ?string $deviceFingerprint = null): array
    {
        $user = $this->lookup($identifier);

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new DomainException(ErrorCode::AUTH_INVALID_CREDENTIALS);
        }

        if ($user->status === UserStatus::SUSPENDED) {
            throw new DomainException(ErrorCode::AUTH_ACCOUNT_SUSPENDED);
        }

        $user->forceFill(['last_login_at' => Carbon::now()])->save();

        $tokens = $this->refreshTokens->issue($user, $deviceFingerprint);

        return ['user' => $user, 'tokens' => $tokens];
    }

    /**
     * Resolve identifier → email or phone lookup.
     * Phone numbers must be presented in the canonical +974XXXXXXXX shape.
     */
    private function lookup(string $identifier): ?User
    {
        $column = str_starts_with($identifier, '+') ? 'phone' : 'email';
        $value = $column === 'email' ? strtolower($identifier) : $identifier;

        /** @var User|null $user */
        $user = User::query()->where($column, $value)->first();

        return $user;
    }
}
