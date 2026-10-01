<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Services\Auth\DeviceContext;
use App\Services\Auth\LoginAttemptLimiter;
use App\Services\Auth\SignInResult;
use Illuminate\Support\Facades\Hash;

/**
 * Password sign-in by email OR Qatari phone, kept for existing clients while
 * they move to email codes (the route is switched off by
 * AUTH_PASSWORD_LOGIN_ENABLED=false).
 */
class LoginUserAction
{
    public function __construct(
        private readonly LoginAttemptLimiter $loginAttempts,
        private readonly CompleteSignInAction $completeSignIn,
    ) {}

    /**
     * @throws DomainException
     */
    public function execute(string $identifier, string $password, DeviceContext $device): SignInResult
    {
        $this->loginAttempts->ensureNotLockedOut($identifier);

        $user = $this->lookup($identifier);

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->loginAttempts->recordFailure($identifier);

            throw new DomainException(ErrorCode::AUTH_INVALID_CREDENTIALS);
        }

        $this->loginAttempts->clear($identifier);

        return $this->completeSignIn->execute($user, $device);
    }

    /**
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
