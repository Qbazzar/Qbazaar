<?php

declare(strict_types=1);

namespace App\Services\Auth;

final readonly class PendingDeviceChallenge
{
    public function __construct(
        public string $userId,
        public DeviceContext $device,
    ) {}
}
