<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Jobs\ProcessAdImagesJob;
use App\Models\Ad;
use App\Services\Media\UploadedFileNamer;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Puts locally drawn photos through the same Media Library path as real
 * uploads, so conversions, BlurHash and perceptual hashes are produced by
 * the production jobs on whichever queue connection the run chose.
 */
final class DemoMedia
{
    private const array COVER_SIZE = [1600, 600];

    private const array CHAT_PHOTO_SIZE = [900, 900];

    private string $connection = 'sync';

    public function __construct(
        private readonly PlaceholderImages $images,
        private readonly UploadedFileNamer $fileNamer,
    ) {}

    public function useConnection(string $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * @return int number of photos attached
     */
    public function attachListingPhotos(Ad $ad, string $color, string $caption, int $count): int
    {
        $mediaIds = [];

        foreach ($this->images->series($color, $caption, $count) as $path) {
            $photo = $this->upload($path);

            $mediaIds[] = (string) $ad->addMedia($photo)
                ->usingFileName($this->fileNamer->nameFor($photo))
                ->toMediaCollection('images')
                ->getKey();
        }

        ProcessAdImagesJob::dispatch($mediaIds)->onConnection($this->connection);

        return count($mediaIds);
    }

    public function coverPhoto(string $caption): UploadedFile
    {
        return $this->upload($this->images->draw('#8A1538', $caption, self::COVER_SIZE));
    }

    public function chatPhoto(string $color, string $caption): UploadedFile
    {
        return $this->upload($this->images->draw($color, $caption, self::CHAT_PHOTO_SIZE));
    }

    public function transferProof(string $caption): UploadedFile
    {
        return $this->upload($this->images->draw('#4A5568', $caption, self::CHAT_PHOTO_SIZE));
    }

    public function cleanUp(): void
    {
        $this->images->cleanUp();
    }

    private function upload(string $path): UploadedFile
    {
        return new UploadedFile($path, basename($path) . '.jpg', 'image/jpeg', null, true);
    }
}
