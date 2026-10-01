<?php

declare(strict_types=1);

namespace App\Services\Media;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

/**
 * Moves a media row's files to the disks its collection is configured for
 * today (e.g. local → R2 after MEDIA_DISK=r2), then repoints the row.
 * Source files are left in place so a rollback is just reverting the env.
 */
class MediaDiskMigrator
{
    /** @var array<string, array{disk: string, conversions_disk: string}> */
    private array $targetsByCollection = [];

    public function migrate(Media $media, bool $dryRun = false): MediaMigrationOutcome
    {
        ['disk' => $targetDisk, 'conversions_disk' => $targetConversionsDisk] = $this->targetsFor($media);

        $sourceDisk = $media->disk;
        $sourceConversionsDisk = $media->conversions_disk ?: $sourceDisk;

        if ($sourceDisk === $targetDisk && $sourceConversionsDisk === $targetConversionsDisk) {
            return MediaMigrationOutcome::AlreadyInPlace;
        }

        $originalPath = $media->getPathRelativeToRoot();

        if (! Storage::disk($sourceDisk)->exists($originalPath)) {
            return MediaMigrationOutcome::SourceMissing;
        }

        if ($dryRun) {
            return MediaMigrationOutcome::Migrated;
        }

        if (! $this->copy($sourceDisk, $targetDisk, $originalPath)) {
            return MediaMigrationOutcome::CopyFailed;
        }

        // Repointing with a conversion missing on the target would break its
        // public URL, so the row stays on the source until a re-run succeeds.
        foreach ($this->derivedFiles($media, $sourceConversionsDisk) as $path) {
            if (! $this->copy($sourceConversionsDisk, $targetConversionsDisk, $path)) {
                return MediaMigrationOutcome::CopyFailed;
            }
        }

        Media::query()->whereKey($media->getKey())->update([
            'disk' => $targetDisk,
            'conversions_disk' => $targetConversionsDisk,
        ]);

        return MediaMigrationOutcome::Migrated;
    }

    /**
     * @return array{disk: string, conversions_disk: string}
     */
    private function targetsFor(Media $media): array
    {
        $key = $media->model_type . '|' . $media->collection_name;

        return $this->targetsByCollection[$key] ??= $this->resolveTargets($media);
    }

    /**
     * @return array{disk: string, conversions_disk: string}
     */
    private function resolveTargets(Media $media): array
    {
        $modelClass = Relation::getMorphedModel($media->model_type) ?? $media->model_type;
        $model = class_exists($modelClass) ? new $modelClass : null;
        $collection = $model instanceof HasMedia ? $model->getMediaCollection($media->collection_name) : null;

        $disk = $collection?->diskName ?: (string) config('media-library.disk_name');

        return [
            'disk' => $disk,
            'conversions_disk' => $collection?->conversionsDiskName ?: $disk,
        ];
    }

    /**
     * @return list<string>
     */
    private function derivedFiles(Media $media, string $disk): array
    {
        $pathGenerator = PathGeneratorFactory::create($media);
        $storage = Storage::disk($disk);

        return array_values(array_merge(
            $storage->files($pathGenerator->getPathForConversions($media)),
            $storage->files($pathGenerator->getPathForResponsiveImages($media)),
        ));
    }

    private function copy(string $fromDisk, string $toDisk, string $path): bool
    {
        // Streaming a file onto itself would truncate it.
        if ($fromDisk === $toDisk) {
            return true;
        }

        $stream = Storage::disk($fromDisk)->readStream($path);

        if (! is_resource($stream)) {
            return false;
        }

        try {
            return Storage::disk($toDisk)->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
