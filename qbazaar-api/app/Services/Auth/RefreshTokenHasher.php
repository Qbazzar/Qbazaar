<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Hash;

/**
 * Hashes refresh tokens with HMAC-SHA256 under a secret derived from the app
 * key. A refresh token already carries 128 bits of randomness, so a slow
 * password hash adds no protection and cost two bcrypt rounds per refresh.
 *
 * Rows written before the switch still hold a bcrypt hash; they verify once
 * through bcrypt and are replaced by an HMAC row on that same rotation.
 */
class RefreshTokenHasher
{
    private const string SECRET_CONTEXT = 'qbazaar:refresh-token';

    public function hash(string $rawToken): string
    {
        return $this->hmac($rawToken, (string) config('app.key'));
    }

    public function check(string $rawToken, string $storedHash): bool
    {
        if ($this->isLegacy($storedHash)) {
            return Hash::check($rawToken, $storedHash);
        }

        // Previous keys keep sessions alive across an APP_KEY rotation.
        foreach ($this->appKeys() as $appKey) {
            if (hash_equals($storedHash, $this->hmac($rawToken, $appKey))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function appKeys(): array
    {
        return array_values(array_filter([
            (string) config('app.key'),
            ...(array) config('app.previous_keys', []),
        ], fn (mixed $key): bool => is_string($key) && $key !== ''));
    }

    private function isLegacy(string $storedHash): bool
    {
        return str_starts_with($storedHash, '$2y$');
    }

    private function hmac(string $rawToken, string $appKey): string
    {
        return hash_hmac('sha256', $rawToken, hash_hmac('sha256', self::SECRET_CONTEXT, $appKey, true));
    }
}
