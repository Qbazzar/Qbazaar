<?php

declare(strict_types=1);

namespace App\Services\Auth\Social;

use App\Enums\SocialProvider;

/**
 * The verified claims of a Google / Apple id_token that sign-in relies on.
 * The email is only ever one the provider marked as verified.
 */
final readonly class SocialIdentity
{
    public function __construct(
        public SocialProvider $provider,
        public string $subject,
        public string $email,
        public ?string $name,
    ) {}
}
