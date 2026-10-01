<?php

declare(strict_types=1);

namespace App\Services\Auth\Social;

use App\Enums\SocialProvider;
use App\Exceptions\DomainException;
use Illuminate\Container\Attributes\Bind;

#[Bind(JwksSocialTokenVerifier::class)]
interface SocialTokenVerifier
{
    /**
     * @throws DomainException AUTH_013 for a token that fails any check,
     *                         AUTH_015 when the provider is not configured or unreachable
     */
    public function verify(SocialProvider $provider, string $idToken): SocialIdentity;
}
