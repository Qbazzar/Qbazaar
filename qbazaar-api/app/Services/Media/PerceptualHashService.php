<?php

declare(strict_types=1);

namespace App\Services\Media;

use GdImage;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * 64-bit difference hash (dHash) for near-duplicate image detection.
 *
 * Why dHash instead of a DCT pHash library: GD is already the project's
 * image backend (see BlurHashGeneratorService), dHash needs no extra
 * dependency, and it is robust against the transforms we care about
 * (re-encode, resize, mild compression). The config key keeps the
 * historical name `phash_distance_threshold`.
 *
 * Hash format: 16 lowercase hex chars (64 bits). Stored on `media.phash`
 * as CHAR(16) so MySQL can compute Hamming distance with
 * BIT_COUNT(CONV(a,16,10) ^ CONV(b,16,10)) without PHP signed-int issues.
 */
class PerceptualHashService
{
    private const SAMPLE_W = 9;

    private const SAMPLE_H = 8;

    public function hash(string $path): ?string
    {
        try {
            $contents = @file_get_contents($path);
            if ($contents === false) {
                return null;
            }

            $source = @imagecreatefromstring($contents);
            if ($source === false) {
                return null;
            }

            $sample = imagescale($source, self::SAMPLE_W, self::SAMPLE_H, IMG_BICUBIC);
            imagedestroy($source);
            if ($sample === false) {
                return null;
            }

            $bits = '';
            for ($y = 0; $y < self::SAMPLE_H; $y++) {
                $previous = $this->luminanceAt($sample, 0, $y);
                for ($x = 1; $x < self::SAMPLE_W; $x++) {
                    $current = $this->luminanceAt($sample, $x, $y);
                    $bits .= $current > $previous ? '1' : '0';
                    $previous = $current;
                }
            }
            imagedestroy($sample);

            return $this->bitsToHex($bits);
        } catch (Throwable $e) {
            Log::warning('phash.failed', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Compute the Hamming distance between two 64-bit dHashes.
     *
     * @param string $hexA 16-char lowercase hex hash produced by hash().
     * @param string $hexB 16-char lowercase hex hash produced by hash().
     *
     * @throws InvalidArgumentException if either argument is not a 16-char lowercase hex string.
     */
    public function distance(string $hexA, string $hexB): int
    {
        if (! preg_match('/^[0-9a-f]{16}$/', $hexA) || ! preg_match('/^[0-9a-f]{16}$/', $hexB)) {
            throw new InvalidArgumentException('distance() expects 16-char lowercase hex hashes.');
        }

        $a = str_pad((string) hex2bin($hexA), 8, "\0", STR_PAD_LEFT);
        $b = str_pad((string) hex2bin($hexB), 8, "\0", STR_PAD_LEFT);

        $distance = 0;
        for ($i = 0; $i < 8; $i++) {
            $distance += substr_count(decbin(ord($a[$i]) ^ ord($b[$i])), '1');
        }

        return $distance;
    }

    /**
     * 64-bit binary string -> 16 hex chars, in 16-bit chunks because
     * bindec() on the full 64 bits would round through float.
     */
    private function bitsToHex(string $bits): string
    {
        $hex = '';
        foreach (str_split($bits, 16) as $chunk) {
            $hex .= str_pad(dechex((int) bindec($chunk)), 4, '0', STR_PAD_LEFT);
        }

        return $hex;
    }

    private function luminanceAt(GdImage $image, int $x, int $y): float
    {
        $index = imagecolorat($image, $x, $y);
        if ($index === false) {
            return 0.0;
        }

        $rgb = imagecolorsforindex($image, $index);

        return 0.299 * $rgb['red'] + 0.587 * $rgb['green'] + 0.114 * $rgb['blue'];
    }
}
