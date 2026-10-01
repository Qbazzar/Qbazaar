<?php

declare(strict_types=1);

namespace App\Enums;

enum SocialProvider: string
{
    case GOOGLE = 'google';
    case APPLE = 'apple';

    public function jwksUrl(): string
    {
        return match ($this) {
            self::GOOGLE => 'https://www.googleapis.com/oauth2/v3/certs',
            self::APPLE => 'https://appleid.apple.com/auth/keys',
        };
    }

    /**
     * @return list<string>
     */
    public function issuers(): array
    {
        return match ($this) {
            self::GOOGLE => ['https://accounts.google.com', 'accounts.google.com'],
            self::APPLE => ['https://appleid.apple.com'],
        };
    }

    /**
     * The OAuth client ids (web, iOS, Android / Services ID, bundle id) whose
     * tokens we accept as the `aud` claim.
     *
     * @return list<string>
     */
    public function clientIds(): array
    {
        $configured = (string) config('services.' . $this->value . '.client_ids');

        return array_values(array_filter(array_map('trim', explode(',', $configured))));
    }
}
