<?php

declare(strict_types=1);

namespace App\Support;

/**
 * IBAN checks per ISO 13616: the shape (country, check digits, account
 * part), the length registered for the country when known, and the
 * mod-97 checksum that catches a mistyped or swapped digit.
 */
final class Iban
{
    /** Countries whose length is enforced; others only need 15 to 34 characters. */
    private const array LENGTHS = [
        'QA' => 29, 'SA' => 24, 'AE' => 23, 'KW' => 30, 'BH' => 22,
        'JO' => 30, 'LB' => 28, 'EG' => 29, 'TR' => 26, 'PS' => 29,
        'GB' => 22, 'DE' => 22, 'FR' => 27,
    ];

    public static function normalize(string $iban): string
    {
        return strtoupper((string) preg_replace('/[\s-]+/', '', $iban));
    }

    public static function isValid(string $iban): bool
    {
        $iban = self::normalize($iban);

        if (preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban) !== 1) {
            return false;
        }

        $expectedLength = self::LENGTHS[substr($iban, 0, 2)] ?? null;

        if ($expectedLength !== null && strlen($iban) !== $expectedLength) {
            return false;
        }

        return self::mod97(substr($iban, 4) . substr($iban, 0, 4)) === 1;
    }

    public static function lastFour(string $iban): string
    {
        return substr(self::normalize($iban), -4);
    }

    /**
     * What responses show: the country and the last four characters.
     */
    public static function mask(string $country, string $lastFour): string
    {
        return $country . '** **** ' . $lastFour;
    }

    /**
     * Letters count as two digits (A = 10 … Z = 35). The remainder is taken
     * a few digits at a time, so no big-number arithmetic is needed.
     */
    private static function mod97(string $rearranged): int
    {
        $remainder = 0;

        foreach (str_split($rearranged) as $character) {
            $digits = ctype_alpha($character) ? (string) (ord($character) - 55) : $character;

            foreach (str_split($digits) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }

        return $remainder;
    }
}
