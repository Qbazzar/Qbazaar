<?php

declare(strict_types=1);

namespace App\Services\Auth\Turnstile;

use Illuminate\Container\Attributes\Bind;

#[Bind(CloudflareTurnstileVerifier::class)]
interface TurnstileVerifier
{
    public function verify(string $token, ?string $remoteIp): bool;
}
