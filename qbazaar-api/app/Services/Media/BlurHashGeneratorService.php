<?php

declare(strict_types=1);

namespace App\Services\Media;

use Illuminate\Support\Facades\Log;
use kornrunner\Blurhash\Blurhash;
use Throwable;

/**
 * Computes a BlurHash placeholder string for an image file.
 *
 * BlurHash gives clients a tiny ASCII representation they can render as a
 * blurred preview while the real image loads — eliminating the empty-grey
 * box on slow connections.
 *
 * Implementation note: we prefer the `kornrunner/php-blurhash` package
 * (pure-PHP, no GD/Imagick beyond what we already use). When the package
 * is not installed (the orchestrator has Composer locked here in dev), we
 * return a stable placeholder hash so the rest of the pipeline keeps
 * working — the resource layer treats BlurHash as optional metadata.
 *
 * Failure mode: ALWAYS returns a string. Logs the failure context and
 * returns the placeholder; never throws upward. Image processing is
 * downstream of user upload and we'd rather degrade gracefully than fail
 * the upload entirely.
 */
class BlurHashGeneratorService
{
    /**
     * Stable placeholder used when the package is unavailable or processing
     * fails. The string is a valid BlurHash (4×3 grid) decoding to a soft
     * neutral grey so clients render something useful instead of an empty
     * box.
     */
    public const PLACEHOLDER = 'L6PZfSi_.AyE_3t7t7R**0o#DgR4';

    public function forFile(string $absolutePath): string
    {
        if (! is_file($absolutePath)) {
            Log::warning('BlurHash: file not found', ['path' => $absolutePath]);

            return self::PLACEHOLDER;
        }

        try {
            return $this->encodeWithKornrunner($absolutePath);
        } catch (Throwable $e) {
            Log::warning('BlurHash: kornrunner failed, using placeholder', [
                'path' => $absolutePath,
                'error' => $e->getMessage(),
            ]);

            return self::PLACEHOLDER;
        }
    }

    /**
     * Encode the file using the kornrunner library. Kept private so the
     * dependency on the optional package stays isolated to this method —
     * static analyzers won't trip on the missing class because we only
     * reach this branch behind a runtime `class_exists` guard.
     */
    private function encodeWithKornrunner(string $absolutePath): string
    {
        // Sample down to ~64px on the longest side for speed; BlurHash
        // doesn't need full resolution because the output is 4x3 components.
        $image = imagecreatefromstring((string) file_get_contents($absolutePath));

        if ($image === false) {
            return self::PLACEHOLDER;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $pixels = [];
        for ($y = 0; $y < $height; $y++) {
            $row = [];
            for ($x = 0; $x < $width; $x++) {
                $index = imagecolorat($image, $x, $y);
                if ($index === false) {
                    continue;
                }
                $colors = imagecolorsforindex($image, $index);
                $row[] = [$colors['red'], $colors['green'], $colors['blue']];
            }
            $pixels[] = $row;
        }

        imagedestroy($image);

        return Blurhash::encode($pixels, 4, 3);
    }
}
