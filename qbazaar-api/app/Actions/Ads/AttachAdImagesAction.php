<?php

declare(strict_types=1);

namespace App\Actions\Ads;

use App\Enums\PlatformSetting;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Jobs\Ads\ModerateAdJob;
use App\Jobs\ProcessAdImagesJob;
use App\Models\Ad;
use App\Services\Media\UploadedFileNamer;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Stores new photos on an ad without letting it exceed the image limit.
 *
 * The count check and the writes share a per-ad lock, so two uploads racing
 * each other cannot both pass the check and push the ad over the limit.
 */
class AttachAdImagesAction
{
    private const LOCK_SECONDS = 120;

    // A concurrent upload for the same ad finishes in about a second now that
    // no conversion runs in the request; waiting longer only ties up a worker.
    private const LOCK_WAIT_SECONDS = 3;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly UploadedFileNamer $fileNamer,
        private readonly ResubmitActiveAdAction $resubmit,
    ) {}

    /**
     * @param list<UploadedFile> $files
     * @return list<Media>
     */
    public function __invoke(Ad $ad, array $files): array
    {
        $created = [];

        try {
            Cache::lock("ads:{$ad->getKey()}:images", self::LOCK_SECONDS)
                ->block(self::LOCK_WAIT_SECONDS, function () use ($ad, $files, &$created): void {
                    $created = $this->attach($ad, $files);
                });
        } catch (LockTimeoutException) {
            throw new DomainException(ErrorCode::REQUEST_IN_PROGRESS);
        }

        ($this->resubmit)($ad);

        // Resubmitted first, so the moderation run at the end of the chain
        // finds the ad pending and compares the freshly computed hashes.
        Bus::chain([
            new ProcessAdImagesJob(array_map(
                static fn (Media $media): string => (string) $media->getKey(),
                $created,
            )),
            new ModerateAdJob((string) $ad->getKey()),
        ])->dispatch();

        // `has_images` is indexed and adding media does not save the ad.
        if ($created !== [] && $ad->shouldBeSearchable()) {
            $ad->searchable();
        }

        return $created;
    }

    /**
     * @param list<UploadedFile> $files
     * @return list<Media>
     */
    private function attach(Ad $ad, array $files): array
    {
        $max = $this->settings->integer(PlatformSetting::AD_MAX_IMAGES);
        $existing = $ad->media()->where('collection_name', 'images')->count();
        $incoming = count($files);

        if ($existing + $incoming > $max) {
            throw new DomainException(
                ErrorCode::UPLOAD_MAX_IMAGES_REACHED,
                __('errors.ad.images.too_many', ['max' => $max]),
                ['existing' => $existing, 'incoming' => $incoming, 'max' => $max],
            );
        }

        return array_map(
            fn (UploadedFile $file): Media => $ad->addMedia($file->getPathname())
                ->usingFileName($this->fileNamer->nameFor($file))
                ->toMediaCollection('images'),
            $files,
        );
    }
}
