<?php

declare(strict_types=1);

use App\Jobs\ProcessAdImagesJob;
use App\Models\Ad;
use App\Models\User;
use App\Services\Media\BlurHashGeneratorService;
use App\Services\Media\PerceptualHashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    // Use real disk so getPath() resolves to an actual filesystem path that GD
    // can read. Storage::fake() swaps the disk root to a temp dir but still
    // writes real files — exactly what PerceptualHashService::hash() needs.
    Storage::fake('public');

    $this->seedReferenceData();

    // Wrap factory creation in withoutSyncingToSearch so the Ad model
    // observer does not attempt to push documents to Meilisearch
    // (Meilisearch is not running in the standard unit-test environment).
    Ad::withoutSyncingToSearch(function (): void {
        $this->seller = User::factory()->create();
    });
});

/**
 * Create a real 32×32 PNG in a system temp file.
 * Returns the path; the caller owns the file (it is deleted on process exit
 * by the OS — no explicit cleanup needed for short-lived test processes).
 */
function makeTempPng(string $prefix): string
{
    $image = imagecreatetruecolor(32, 32);
    assert($image !== false);
    $colour = imagecolorallocate($image, 30, 120, 220);
    assert($colour !== false);
    imagefill($image, 0, 0, $colour);

    $path = tempnam(sys_get_temp_dir(), $prefix) . '.png';
    imagepng($image, $path);
    imagedestroy($image);

    return $path;
}

it('populates phash on media after the job runs', function (): void {
    /** @var Ad $ad */
    $ad = Ad::withoutSyncingToSearch(fn () => $this->makeAd($this->seller));

    $tmpPath = makeTempPng('phash_test_');

    /** @var Media $media */
    $media = $ad->addMedia($tmpPath)
        ->usingFileName('test.png')
        ->toMediaCollection('images');

    (new ProcessAdImagesJob([(string) $media->getKey()]))->handle(
        app(BlurHashGeneratorService::class),
        app(PerceptualHashService::class),
    );

    $fresh = $media->fresh();
    assert($fresh !== null);

    expect($fresh->phash)->toMatch('/^[0-9a-f]{16}$/');
});

it('completes without throwing when the media file is missing and leaves phash null', function (): void {
    /** @var Ad $ad */
    $ad = Ad::withoutSyncingToSearch(fn () => $this->makeAd($this->seller));

    $tmpPath = makeTempPng('phash_missing_');

    /** @var Media $media */
    $media = $ad->addMedia($tmpPath)
        ->usingFileName('missing.png')
        ->toMediaCollection('images');

    // Delete all stored files so getPath() points to a non-existent path.
    // This mirrors the blurhash graceful-degradation contract: the job must
    // complete without throwing, and phash stays null.
    Storage::disk('public')->deleteDirectory('');

    expect(fn () => (new ProcessAdImagesJob([(string) $media->getKey()]))->handle(
        app(BlurHashGeneratorService::class),
        app(PerceptualHashService::class),
    ))->not->toThrow(Throwable::class);

    $fresh = $media->fresh();
    assert($fresh !== null);

    expect($fresh->phash)->toBeNull();
});
