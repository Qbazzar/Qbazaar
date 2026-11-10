<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Actions\Account\UploadAvatarAction;
use App\Actions\Social\ReplaceBusinessCoverAction;
use App\Jobs\ProcessAdImagesJob;
use App\Models\Ad;
use App\Models\User;
use App\Services\Media\UploadedFileNamer;
use Database\Seeders\Demo\Content\DesignManifest;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Puts the design's photos (and a few locally drawn ones for receipts) through
 * the same Media Library path as real uploads, so conversions, BlurHash and
 * perceptual hashes are produced by the production jobs on whichever queue
 * connection the run chose. Design files are copied before upload because
 * Media Library moves what it is given.
 */
final class DemoMedia
{
    /** Custom property that marks a photo as one of the design's, holding its manifest key. */
    public const string DESIGN_PROPERTY = 'design_image';

    private const array CHAT_PHOTO_SIZE = [900, 900];

    private string $connection = 'sync';

    public function __construct(
        private readonly PlaceholderImages $images,
        private readonly UploadedFileNamer $fileNamer,
        private readonly DesignManifest $design,
    ) {}

    public function useConnection(string $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * @param list<string> $imageKeys design photo keys, in display order
     * @return int number of photos attached
     */
    public function attachDesignPhotos(Ad $ad, array $imageKeys): int
    {
        $mediaIds = [];

        foreach ($imageKeys as $key) {
            $photo = $this->designFile($key);

            $mediaIds[] = (string) $ad->addMedia($photo)
                ->usingFileName($this->fileNamer->nameFor($photo))
                ->withCustomProperties([self::DESIGN_PROPERTY => $key])
                ->toMediaCollection('images')
                ->getKey();
        }

        ProcessAdImagesJob::dispatch($mediaIds)->onConnection($this->connection);

        return count($mediaIds);
    }

    /**
     * Makes the ad's gallery exactly these design photos, in this order.
     * The gallery is left alone when it already is, so reruns cost nothing.
     *
     * @param list<string> $imageKeys
     * @return bool whether the gallery changed
     */
    public function syncDesignPhotos(Ad $ad, array $imageKeys): bool
    {
        if ($this->designKeysOf($ad->getMedia('images')) === $imageKeys) {
            return false;
        }

        $ad->clearMediaCollection('images');
        $ad->unsetRelation('media');
        $this->attachDesignPhotos($ad, $imageKeys);

        return true;
    }

    public function syncAvatar(User $user, string $imageKey): bool
    {
        if ($user->getFirstMedia('avatar')?->getCustomProperty(self::DESIGN_PROPERTY) === $imageKey) {
            return false;
        }

        $media = app(UploadAvatarAction::class)->execute($user, $this->designFile($imageKey));
        $media->setCustomProperty(self::DESIGN_PROPERTY, $imageKey)->save();
        $user->unsetRelation('media');

        return true;
    }

    public function syncCover(User $user, string $imageKey): bool
    {
        if ($user->getFirstMedia(User::BUSINESS_COVER_COLLECTION)?->getCustomProperty(self::DESIGN_PROPERTY) === $imageKey) {
            return false;
        }

        $media = app(ReplaceBusinessCoverAction::class)->upload($user, $this->designFile($imageKey));
        $media->setCustomProperty(self::DESIGN_PROPERTY, $imageKey)->save();
        $user->unsetRelation('media');

        return true;
    }

    /**
     * @param iterable<Media> $media
     * @return list<string|null> the design key of each photo, null for photos that are not the design's
     */
    public function designKeysOf(iterable $media): array
    {
        $keys = [];

        foreach ($media as $item) {
            $keys[] = $item->getCustomProperty(self::DESIGN_PROPERTY);
        }

        return $keys;
    }

    public function designChatPhoto(string $imageKey): UploadedFile
    {
        return $this->designFile($imageKey);
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

    private function designFile(string $imageKey): UploadedFile
    {
        $copy = tempnam(sys_get_temp_dir(), 'qb-design-');

        if ($copy === false || ! copy($this->design->imagePath($imageKey), $copy)) {
            throw new RuntimeException("Could not copy design image [{$imageKey}] to the temp directory.");
        }

        return new UploadedFile($copy, $imageKey . '.jpg', 'image/jpeg', null, true);
    }

    private function upload(string $path): UploadedFile
    {
        return new UploadedFile($path, basename($path) . '.jpg', 'image/jpeg', null, true);
    }
}
