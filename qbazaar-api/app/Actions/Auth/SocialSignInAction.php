<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\SocialProvider;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Auth\DeviceContext;
use App\Services\Auth\SignInResult;
use App\Services\Auth\Social\SocialIdentity;
use App\Services\Auth\Social\SocialTokenVerifier;

/**
 * Google / Apple sign-in with an id_token. The provider subject is matched
 * first; otherwise the provider-verified email links the existing account.
 * A new person signs up with the same details as the email-code flow, and
 * their phone stays unverified until they prove it by SMS.
 */
class SocialSignInAction
{
    public function __construct(
        private readonly SocialTokenVerifier $verifier,
        private readonly RegisterUserAction $registerUser,
        private readonly CompleteSignInAction $completeSignIn,
    ) {}

    /**
     * @param array{full_name: string, phone: string, account_type: string, language?: string}|null $registration
     *
     * @throws DomainException
     */
    public function execute(SocialProvider $provider, string $idToken, ?array $registration, DeviceContext $device): SignInResult
    {
        $identity = $this->verifier->verify($provider, $idToken);

        /** @var SocialAccount|null $account */
        $account = SocialAccount::query()
            ->with('user')
            ->where('provider', $provider)
            ->where('provider_user_id', $identity->subject)
            ->first();

        /** @var User|null $user */
        $user = $account->user ?? User::query()->where('email', $identity->email)->first();

        if ($user === null) {
            return $this->signUp($identity, $registration, $device);
        }

        if ($account === null) {
            $this->link($user, $identity);
        }

        if (! $user->email_verified && $user->email === $identity->email) {
            $user->forceFill(['email_verified' => true])->save();
        }

        return $this->completeSignIn->execute($user, $device);
    }

    /**
     * @param array{full_name: string, phone: string, account_type: string, language?: string}|null $registration
     *
     * @throws DomainException
     */
    private function signUp(SocialIdentity $identity, ?array $registration, DeviceContext $device): SignInResult
    {
        if ($registration === null) {
            throw new DomainException(ErrorCode::AUTH_REGISTRATION_REQUIRED, details: [
                'email' => $identity->email,
                'full_name' => $identity->name,
            ]);
        }

        $created = $this->registerUser->execute([...$registration, 'email' => $identity->email], $device, emailVerified: true);

        $this->link($created['user'], $identity);

        return SignInResult::registered($created['user'], $created['tokens']);
    }

    private function link(User $user, SocialIdentity $identity): void
    {
        SocialAccount::query()->firstOrCreate(
            ['provider' => $identity->provider, 'provider_user_id' => $identity->subject],
            ['user_id' => $user->id, 'email' => $identity->email],
        );
    }
}
