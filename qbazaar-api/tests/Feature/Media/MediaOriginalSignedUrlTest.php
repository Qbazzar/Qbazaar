<?php

declare(strict_types=1);

use App\Http\Resources\Api\V1\Media\MediaResource;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    // Real (faked-root) disk so addMedia can write actual files for the
    // non-queued conversions — same rationale as ProcessAdImagesJobTest.
    Storage::fake('public');

    $this->seedReferenceData();

    // Wrap factory creation in withoutSyncingToSearch so the Ad observer
    // never pushes documents to Meilisearch from fixture setup.
    Ad::withoutSyncingToSearch(function (): void {
        $this->seller = User::factory()->create();
    });

    $this->makeAdWithImage = function (): array {
        /** @var Ad $ad */
        $ad = Ad::withoutSyncingToSearch(fn (): Ad => $this->makeAd($this->seller));

        /** @var Media $media */
        $media = $ad->addMedia(makeSignedUrlTestPng())
            ->usingFileName('original.png')
            ->toMediaCollection('images');

        return [$ad, $media];
    };
});

/**
 * Create a real 32×32 PNG in a system temp file and return its path.
 * Named distinctly from the other media test helpers because Pest loads
 * every test file into one process — duplicate global function names
 * would fatal.
 */
function makeSignedUrlTestPng(): string
{
    $image = imagecreatetruecolor(32, 32);
    assert($image !== false);
    $colour = imagecolorallocate($image, 60, 160, 90);
    assert($colour !== false);
    imagefill($image, 0, 0, $colour);

    $stub = tempnam(sys_get_temp_dir(), 'signed_url_test_');
    assert($stub !== false);
    unlink($stub);

    $path = $stub . '.png';
    imagepng($image, $path);
    imagedestroy($image);

    return $path;
}

it('serializes the original as a temporary signed url while sizes stay public', function (): void {
    [, $media] = ($this->makeAdWithImage)();

    $payload = (new MediaResource($media))->toArray(request());

    expect($payload['url'])
        ->toContain("/api/v1/media/{$media->getKey()}/original")
        ->toContain('signature=')
        ->toContain('expires=');

    foreach ($payload['sizes'] as $name => $sizeUrl) {
        expect($sizeUrl)->not->toContain('signature=', "sizes.{$name} must stay a plain public URL");
    }
});

it('serves the original file when the signed url is valid', function (): void {
    [, $media] = ($this->makeAdWithImage)();

    $payload = (new MediaResource($media))->toArray(request());

    $response = $this->get($payload['url']);

    $response->assertOk();
    expect((string) $response->headers->get('content-type'))->toStartWith('image/');
});

it('rejects a tampered signature with 403', function (): void {
    [, $media] = ($this->makeAdWithImage)();

    $payload = (new MediaResource($media))->toArray(request());

    $tampered = preg_replace('/signature=\w+/', 'signature=' . str_repeat('0', 64), $payload['url']);
    assert(is_string($tampered));

    $this->get($tampered)->assertForbidden();
});

it('rejects an expired signed url with 403', function (): void {
    [, $media] = ($this->makeAdWithImage)();

    $payload = (new MediaResource($media))->toArray(request());

    $this->travel((int) config('qbazaar.uploads.original_url_ttl_hours') + 1)->hours();

    $this->get($payload['url'])->assertForbidden();
});

it('returns 404 for media outside the images collection', function (): void {
    // Avatars live in their own collection — the original route must only
    // ever serve ad images, even when the signature itself is valid.
    /** @var Media $avatar */
    $avatar = $this->seller->addMedia(makeSignedUrlTestPng())
        ->usingFileName('avatar.png')
        ->toMediaCollection('avatar');

    $url = URL::temporarySignedRoute(
        'api.v1.media.original',
        now()->addHour(),
        ['media' => $avatar->getKey()],
    );

    $this->get($url)->assertNotFound();
});

it('returns 404 for a nonexistent media id even with a valid signature', function (): void {
    $url = URL::temporarySignedRoute(
        'api.v1.media.original',
        now()->addHour(),
        ['media' => 999_999],
    );

    $this->get($url)->assertNotFound();
});

it('returns 404 when the original file is missing on disk', function (): void {
    [, $media] = ($this->makeAdWithImage)();

    $payload = (new MediaResource($media))->toArray(request());

    Storage::disk('public')->delete($media->getPathRelativeToRoot());

    $this->get($payload['url'])->assertNotFound();
});
