<?php

declare(strict_types=1);

namespace App\Services\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The queued half of an ad photo upload: bounds the stored original
 * ({@see OriginalImageDownscaler}), then records the image's dimensions,
 * BlurHash and perceptual hash on the media row. Safe to repeat — a shrunk
 * original is left alone and a failed hash never overwrites a valid one.
 */
class AdImageProcessor
{
    public function __construct(
        private readonly MediaStorage $storage,
        private readonly OriginalImageDownscaler $downscaler,
        private readonly BlurHashGeneratorService $blurHasher,
        private readonly PerceptualHashService $perceptualHasher,
    ) {}

    /**
     * @return bool false when the original file is missing
     */
    public function process(Media $media): bool
    {
        $found = $this->storage->withLocalCopy($media, function (string $path) use ($media): bool {
            $this->boundOriginal($media, $path);
            $this->recordMetadata($media, $path);

            return true;
        });

        if ($found === null) {
            return false;
        }

        $media->save();

        return true;
    }

    private function boundOriginal(Media $media, string $path): void
    {
        $extension = strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION));

        if (! $this->downscaler->shrink($path, $extension)) {
            return;
        }

        $this->storage->storeOriginal($media, $path);
        $media->size = (int) filesize($path);
    }

    private function recordMetadata(Media $media, string $path): void
    {
        $media->setCustomProperty('blurhash', $this->blurHasher->forFile($path));

        $size = $this->displayedSize($path);
        if ($size !== null) {
            $media->setCustomProperty('width', $size[0]);
            $media->setCustomProperty('height', $size[1]);
        }

        $phash = $this->perceptualHasher->hash($path);
        if ($phash !== null) {
            $media->phash = $phash;
            $media->phash_int = $this->perceptualHasher->toInteger($phash);
        }
    }

    /**
     * Width and height as shown, i.e. swapped when the EXIF orientation
     * rotates the photo by 90 degrees.
     *
     * @return array{int, int}|null
     */
    private function displayedSize(string $path): ?array
    {
        $size = @getimagesize($path);

        if ($size === false) {
            return null;
        }

        $exif = function_exists('exif_read_data') ? @exif_read_data($path) : false;
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        return in_array($orientation, [5, 6, 7, 8], true) ? [$size[1], $size[0]] : [$size[0], $size[1]];
    }
}
