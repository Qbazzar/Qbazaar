<?php

declare(strict_types=1);

namespace App\Services\Media;

use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

/**
 * Bounds the stored size of uploaded originals: an image whose longest side
 * exceeds `qbazaar.uploads.original_max_side_px` is shrunk in place. Runs
 * on the queue only — decoding a phone photo takes tens of MB and up to a
 * second, which an upload request must not pay for.
 */
class OriginalImageDownscaler
{
    private const QUALITY = 85;

    /**
     * The resized file is written next to $path and renamed over it, so a
     * conversion job reading the original never sees a half-written file.
     *
     * @return bool whether the file was replaced
     */
    public function shrink(string $path, string $extension): bool
    {
        $maxSide = (int) config('qbazaar.uploads.original_max_side_px');
        $size = @getimagesize($path);

        if ($size === false || max($size[0], $size[1]) <= $maxSide) {
            return false;
        }

        $resized = dirname($path) . DIRECTORY_SEPARATOR . '.' . Str::random(16) . '.' . $extension;

        try {
            Image::useImageDriver((string) config('media-library.image_driver', 'gd'))
                ->loadFile($path)
                ->fit(Fit::Max, $maxSide, $maxSide)
                ->quality(self::QUALITY)
                ->save($resized);

            $replaced = rename($resized, $path);
        } finally {
            if (is_file($resized)) {
                @unlink($resized);
            }
        }

        clearstatcache(true, $path);

        return $replaced;
    }
}
