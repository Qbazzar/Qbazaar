<?php

declare(strict_types=1);

namespace App\Services\Media;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Where a media file lives and how clients reach it, independent of whether
 * the disk is local or Cloudflare R2.
 */
class MediaStorage
{
    public function isLocal(Media $media): bool
    {
        return config("filesystems.disks.{$media->disk}.driver") === 'local';
    }

    /**
     * Expiring API link to the original file. Originals may sit on a private
     * disk, so this is the only URL clients get for them.
     */
    public function signedOriginalUrl(Media $media): string
    {
        return URL::temporarySignedRoute(
            'api.v1.media.original',
            now()->addHours((int) config('qbazaar.uploads.original_url_ttl_hours')),
            ['media' => $media->getKey()],
        );
    }

    /**
     * Expiring API link to a conversion kept on a private disk, falling back
     * to the signed original while the conversion is still queued.
     */
    public function signedConversionUrl(Media $media, string $conversion): string
    {
        if (! $media->hasGeneratedConversion($conversion)) {
            return $this->signedOriginalUrl($media);
        }

        return URL::temporarySignedRoute(
            'api.v1.media.conversion',
            now()->addHours((int) config('qbazaar.uploads.original_url_ttl_hours')),
            ['media' => $media->getKey(), 'conversion' => $conversion],
        );
    }

    public function presignedOriginalUrl(Media $media): string
    {
        return $media->getTemporaryUrl(
            now()->addMinutes((int) config('qbazaar.uploads.original_redirect_ttl_minutes')),
        );
    }

    /**
     * Falls back to the signed original while a conversion is still pending,
     * never to the raw original URL, which is unreachable on a private disk.
     */
    public function conversionUrl(Media $media, string $conversion): string
    {
        return $media->hasGeneratedConversion($conversion)
            ? $media->getUrl($conversion)
            : $this->signedOriginalUrl($media);
    }

    /**
     * Runs $callback with a readable local path to the original, downloading
     * a temporary copy when the disk is remote. Returns null when the file
     * does not exist.
     *
     * @template TResult
     *
     * @param callable(string): TResult $callback
     * @return TResult|null
     */
    public function withLocalCopy(Media $media, callable $callback): mixed
    {
        if ($this->isLocal($media)) {
            $path = $media->getPath();

            return is_file($path) ? $callback($path) : null;
        }

        $stream = Storage::disk($media->disk)->readStream($media->getPathRelativeToRoot());

        if (! is_resource($stream)) {
            return null;
        }

        $temporaryPath = (string) tempnam(sys_get_temp_dir(), 'media_');

        try {
            file_put_contents($temporaryPath, $stream);

            return $callback($temporaryPath);
        } finally {
            fclose($stream);
            @unlink($temporaryPath);
        }
    }
}
