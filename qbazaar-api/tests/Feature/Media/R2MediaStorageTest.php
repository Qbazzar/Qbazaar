<?php

declare(strict_types=1);

use App\Http\Resources\Api\V1\Media\MediaResource;
use App\Jobs\ProcessAdImagesJob;
use App\Models\Ad;
use App\Models\User;
use App\Services\Media\BlurHashGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->freezeTime();

    Storage::fake('public');
    Storage::fake('r2');
    Storage::fake('r2_public');

    config([
        'media-library.disk_name' => 'r2',
        'qbazaar.uploads.public_disk' => 'r2_public',
    ]);

    Storage::disk('r2')->buildTemporaryUrlsUsing(
        fn (string $path, DateTimeInterface $expiresAt): string => "https://r2.test/{$path}?expires={$expiresAt->getTimestamp()}",
    );

    $this->seedReferenceData();

    Ad::withoutSyncingToSearch(function (): void {
        $this->seller = User::factory()->create();
    });

    $this->addAdImage = function (): Media {
        /** @var Ad $ad */
        $ad = Ad::withoutSyncingToSearch(fn (): Ad => $this->makeAd($this->seller));

        return $ad->addMedia(makeR2TestPng())
            ->usingFileName('photo.png')
            ->toMediaCollection('images');
    };
});

function makeR2TestPng(): string
{
    $image = imagecreatetruecolor(32, 32);
    assert($image !== false);
    $colour = imagecolorallocate($image, 200, 90, 40);
    assert($colour !== false);
    imagefill($image, 0, 0, $colour);

    $stub = tempnam(sys_get_temp_dir(), 'r2_test_');
    assert($stub !== false);
    unlink($stub);

    $path = $stub . '.png';
    imagepng($image, $path);
    imagedestroy($image);

    return $path;
}

it('stores ad image originals on the private disk and conversions on the public disk', function (): void {
    $media = ($this->addAdImage)();

    expect($media->disk)->toBe('r2')
        ->and($media->conversions_disk)->toBe('r2_public');

    Storage::disk('r2')->assertExists($media->getPathRelativeToRoot());
    Storage::disk('public')->assertMissing($media->getPathRelativeToRoot());

    foreach (['thumbnail', 'medium', 'large', 'original_webp'] as $conversion) {
        Storage::disk('r2_public')->assertExists($media->getPathRelativeToRoot($conversion));
        Storage::disk('r2')->assertMissing($media->getPathRelativeToRoot($conversion));
    }
});

it('keeps the original behind a signed api link and the sizes on public urls', function (): void {
    $media = ($this->addAdImage)()->refresh();

    $payload = (new MediaResource($media))->toArray(request());

    expect($payload['url'])->toContain("/api/v1/media/{$media->getKey()}/original")
        ->and($payload['url'])->toContain('signature=')
        ->and($payload['sizes']['thumbnail'])->not->toContain('signature=');
});

it('redirects the signed original route to a short-lived presigned url on r2', function (): void {
    $media = ($this->addAdImage)();
    $payload = (new MediaResource($media))->toArray(request());

    $response = $this->get($payload['url']);

    $expectedExpiry = now()->addMinutes((int) config('qbazaar.uploads.original_redirect_ttl_minutes'))->getTimestamp();

    $response->assertRedirect("https://r2.test/{$media->getPathRelativeToRoot()}?expires={$expectedExpiry}");
});

it('stores avatars entirely on the public disk', function (): void {
    $avatar = $this->seller->addMedia(makeR2TestPng())
        ->usingFileName('avatar.png')
        ->toMediaCollection('avatar');

    expect($avatar->disk)->toBe('r2_public');
    Storage::disk('r2_public')->assertExists($avatar->getPathRelativeToRoot());
    Storage::disk('r2')->assertMissing($avatar->getPathRelativeToRoot());
});

it('computes image hashes from a remote original', function (): void {
    $media = ($this->addAdImage)();

    app()->call([new ProcessAdImagesJob([(string) $media->getKey()]), 'handle']);

    $fresh = $media->fresh();
    assert($fresh !== null);

    expect($fresh->phash)->toMatch('/^[0-9a-f]{16}$/')
        ->and($fresh->getCustomProperty('blurhash'))->not->toBe(BlurHashGeneratorService::PLACEHOLDER);
});

it('writes a downscaled original back to the private disk', function (): void {
    config(['qbazaar.uploads.original_max_side_px' => 16]);
    $media = ($this->addAdImage)();

    ProcessAdImagesJob::dispatchSync([(string) $media->getKey()]);

    $stored = getimagesizefromstring((string) Storage::disk('r2')->get($media->getPathRelativeToRoot()));

    expect($stored)->not->toBeFalse()
        ->and([$stored[0], $stored[1]])->toBe([16, 16])
        ->and($media->refresh()->size)->toBe(Storage::disk('r2')->size($media->getPathRelativeToRoot()));
});
