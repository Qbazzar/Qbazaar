<?php

declare(strict_types=1);

namespace App\Services\Admin\TwoFactor;

use App\Support\Base32;
use InvalidArgumentException;

/**
 * Time-based one-time passwords (RFC 6238 over RFC 4226 HOTP) with the
 * parameters every authenticator app supports: HMAC-SHA1, 6 digits and a
 * 30-second step. Implemented here because the project carries no 2FA
 * library and Fortify would bring a second login stack for one feature.
 */
final class Totp
{
    public const int DIGITS = 6;

    public const int PERIOD = 30;

    private const int SECRET_BYTES = 20;

    public function generateSecret(): string
    {
        return Base32::encode(random_bytes(self::SECRET_BYTES));
    }

    /**
     * The time step the code matches, searched `$window` steps either side of
     * now to absorb clock drift, or null. Steps at or before `$lastUsedStep`
     * never match, so an observed code cannot be replayed.
     */
    public function verify(string $secret, string $code, int $timestamp, int $window = 1, ?int $lastUsedStep = null): ?int
    {
        if (preg_match('/^\d{' . self::DIGITS . '}$/', $code) !== 1) {
            return null;
        }

        try {
            $key = Base32::decode($secret);
        } catch (InvalidArgumentException) {
            return null;
        }

        $current = intdiv($timestamp, self::PERIOD);

        for ($step = $current - $window; $step <= $current + $window; $step++) {
            if ($lastUsedStep !== null && $step <= $lastUsedStep) {
                continue;
            }

            if (hash_equals($this->codeAt($key, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    public function codeFor(string $secret, int $timestamp): string
    {
        return $this->codeAt(Base32::decode($secret), intdiv($timestamp, self::PERIOD));
    }

    /**
     * The otpauth:// URI that authenticator apps import.
     */
    public function provisioningUri(string $issuer, string $account, string $secret): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($account);

        return 'otpauth://totp/' . $label . '?' . http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function codeAt(string $key, int $step): string
    {
        // The counter is a big-endian 64-bit integer.
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $offset = ord($hash[19]) & 0x0F;

        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }
}
