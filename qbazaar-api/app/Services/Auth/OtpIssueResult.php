<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * Value object returned by OtpService::issue() — bundles the freshly-minted
 * raw code and its timing metadata so the caller can both hand it to the
 * delivery channel and shape the response without re-deriving them.
 */
final readonly class OtpIssueResult
{
    public function __construct(
        public string $recipient,
        public string $rawCode,
        public int $expiresIn,
        public int $canResendIn,
    ) {}
}
