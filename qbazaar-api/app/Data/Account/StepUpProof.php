<?php

declare(strict_types=1);

namespace App\Data\Account;

/**
 * What the user typed to confirm a sensitive account change: their current
 * password, or the step-up code emailed by POST /account/reauth-code.
 * Exactly one of the two is set, so callers never have to handle both.
 */
final readonly class StepUpProof
{
    private function __construct(
        public ?string $password,
        public ?string $reauthCode,
    ) {}

    public static function password(string $password): self
    {
        return new self($password, null);
    }

    public static function reauthCode(string $code): self
    {
        return new self(null, $code);
    }
}
