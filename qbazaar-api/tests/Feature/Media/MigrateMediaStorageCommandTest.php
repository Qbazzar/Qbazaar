<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('r2');
    Storage::fake('r2_public');

    config([
        'media-library.disk_name' => 'public',
        'qbazaar.uploads.public_disk' => 'public',
    ]);

    $this->seedReferenceData();

    Ad::withoutSyncingToSearch(function (): void {
        $this->seller = User::factory()->create();
    });

    /** @var Ad $ad */
    $ad = Ad::withoutSyncingToSearch(fn (): Ad => $this->makeAd($this->seller));

    $this->adImage = $ad->addMedia(makeMigrationTestPng())
        ->usingFileName('photo.png')
        ->toMediaCollection('images');

    $this->avatar = $this->seller->addMedia(makeMigrationTestPng())
        ->usingFileName('avatar.png')
        ->toMediaCollection('avatar');

    $this->switchToR2 = function (): void {
        config([
            'media-library.disk_name' => 'r2',
            'qbazaar.uploads.public_disk' => 'r2_public',
        ]);
    };
});

function makeMigrationTestPng(): string
{
    $image = imagecreatetruecolor(16, 16);
    assert($image !== false);

    $stub = tempnam(sys_get_temp_dir(), 'media_migration_test_');
    assert($stub !== false);
    unlink($stub);

    $path = $stub . '.png';
    imagepng($image, $path);
    imagedestroy($image);

    return $path;
}

it('copies local media to r2 and repoints the rows', function (): void {
    ($this->switchToR2)();

    $this->artisan('media:migrate-storage')->assertSuccessful();

    /** @var Media $adImage */
    $adImage = $this->adImage->fresh();
    /** @var Media $avatar */
    $avatar = $this->avatar->fresh();

    expect($adImage->disk)->toBe('r2')
        ->and($adImage->conversions_disk)->toBe('r2_public')
        ->and($avatar->disk)->toBe('r2_public')
        ->and($avatar->conversions_disk)->toBe('r2_public');

    Storage::disk('r2')->assertExists($adImage->getPathRelativeToRoot());
    Storage::disk('r2_public')->assertExists($adImage->getPathRelativeToRoot('thumbnail'));
    Storage::disk('r2_public')->assertExists($avatar->getPathRelativeToRoot());
    Storage::disk('r2_public')->assertExists($avatar->getPathRelativeToRoot('thumb'));

    // Sources stay for rollback.
    Storage::disk('public')->assertExists($adImage->getPathRelativeToRoot());
});

it('is idempotent', function (): void {
    ($this->switchToR2)();

    $this->artisan('media:migrate-storage')->assertSuccessful();

    $this->artisan('media:migrate-storage')
        ->expectsTable(['Outcome', 'Media'], [
            ['Migrated', 0],
            ['Already in place', 2],
            ['Source file missing', 0],
            ['Copy failed', 0],
        ])
        ->assertSuccessful();
});

it('changes nothing on a dry run', function (): void {
    ($this->switchToR2)();

    $this->artisan('media:migrate-storage', ['--dry-run' => true])
        ->expectsTable(['Outcome', 'Media'], [
            ['Would migrate', 2],
            ['Already in place', 0],
            ['Source file missing', 0],
            ['Copy failed', 0],
        ])
        ->assertSuccessful();

    expect($this->adImage->fresh()?->disk)->toBe('public');
    Storage::disk('r2')->assertMissing($this->adImage->getPathRelativeToRoot());
});

it('leaves media with a missing source untouched and reports it', function (): void {
    Storage::disk('public')->delete($this->adImage->getPathRelativeToRoot());
    ($this->switchToR2)();

    $this->artisan('media:migrate-storage')
        ->expectsOutputToContain((string) $this->adImage->getKey())
        ->assertSuccessful();

    expect($this->adImage->fresh()?->disk)->toBe('public')
        ->and($this->avatar->fresh()?->disk)->toBe('r2_public');
});

it('does nothing while the configured disks are unchanged', function (): void {
    $this->artisan('media:migrate-storage')->assertSuccessful();

    expect($this->adImage->fresh()?->disk)->toBe('public');
    expect(Storage::disk('r2')->allFiles())->toBe([]);
});

it('keeps a row on the old disk and fails when a conversion cannot be copied', function (): void {
    ($this->switchToR2)();

    $rejectingDisk = Mockery::mock(Storage::disk('r2_public'))->makePartial();
    $rejectingDisk->shouldReceive('writeStream')->andReturnFalse();
    Storage::set('r2_public', $rejectingDisk);

    $this->artisan('media:migrate-storage')
        ->expectsOutputToContain((string) $this->adImage->getKey())
        ->assertFailed();

    expect($this->adImage->fresh()?->disk)->toBe('public')
        ->and($this->adImage->fresh()?->conversions_disk)->toBe('public');
});
