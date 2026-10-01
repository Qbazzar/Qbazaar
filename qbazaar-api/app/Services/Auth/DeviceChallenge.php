<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * What a client needs to finish a sign-in from a new device: the opaque
 * token to send back with the SMS code, and where the code went.
 */
final readonly class DeviceChallenge
{
    public function __construct(
        public string $token,
        public string $maskedPhone,
        public int $expiresIn,
        public int $canResendIn,
    ) {}
}
