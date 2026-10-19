<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * RFC 4648 base32 without padding, the encoding authenticator apps expect
 * for TOTP secrets. Decoding ignores case, spaces and hyphens so a key typed
 * in groups still reads.
 */
final class Base32
{
    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function encode(string $bytes): string
    {
        $encoded = '';
        $buffer = 0;
        $bits = 0;

        for ($i = 0, $length = strlen($bytes); $i < $length; $i++) {
            $buffer = ($buffer << 8) | ord($bytes[$i]);
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $encoded .= self::ALPHABET[($buffer >> $bits) & 0x1F];
            }

            $buffer &= (1 << $bits) - 1;
        }

        // The last group is padded with zero bits on the right.
        if ($bits > 0) {
            $encoded .= self::ALPHABET[($buffer << (5 - $bits)) & 0x1F];
        }

        return $encoded;
    }

    /**
     * @throws InvalidArgumentException when a character is outside the alphabet
     */
    public static function decode(string $encoded): string
    {
        $clean = strtoupper(str_replace([' ', '-', '='], '', $encoded));
        $bytes = '';
        $buffer = 0;
        $bits = 0;

        for ($i = 0, $length = strlen($clean); $i < $length; $i++) {
            $value = strpos(self::ALPHABET, $clean[$i]);
            if ($value === false) {
                throw new InvalidArgumentException('Invalid base32 character.');
            }

            $buffer = ($buffer << 5) | $value;
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $bytes .= chr(($buffer >> $bits) & 0xFF);
                $buffer &= (1 << $bits) - 1;
            }
        }

        // Leftover bits shorter than a byte are padding.
        return $bytes;
    }
}
