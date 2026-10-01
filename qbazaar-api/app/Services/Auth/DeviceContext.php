<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * The device a sign-in comes from: a hashed identifier (never the raw value
 * the client sent), a readable label for the sessions screen, and the IP.
 */
final readonly class DeviceContext
{
    public function __construct(
        public string $hash,
        public string $label,
        public string $ip,
    ) {}
}
