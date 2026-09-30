<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

const ULID_FILE_NAME = '/^[0-9A-HJKMNP-TV-Z]{26}\.(jpg|jpeg|png|webp)$/';

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Bus::fake();

    $this->seedReferenceData();
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);
});

/**
 * Fake uploads report a MIME type derived from their name, which would hide
 * exactly the name/content mismatch under test, so build real uploads whose
 * type is sniffed from the bytes.
 */
function uploadWithContent(string $clientName, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, $content);

    return new UploadedFile($path, $clientName, null, null, true);
}

function jpegNamed(string $clientName): UploadedFile
{
    return uploadWithContent($clientName, UploadedFile::fake()->image('source.jpg', 64, 64)->getContent());
}

function htmlDisguisedAsImage(): UploadedFile
{
    return uploadWithContent('photo.jpg', '<html><body><script>alert(1)</script></body></html>');
}

function svgImage(): UploadedFile
{
    return uploadWithContent(
        'logo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="1" height="1"/></svg>',
    );
}

it('ignores the client file name for ad images', function (string $clientName): void {
    $ad = $this->makeAd($this->user, ['status' => AdStatus::DRAFT->value]);

    postJson("/api/v1/ads/{$ad->id}/images", ['images' => [jpegNamed($clientName)]])
        ->assertCreated();

    $media = $ad->fresh()->getFirstMedia('images');

    expect($media->file_name)->toMatch(ULID_FILE_NAME)
        ->and(file_exists($media->getPath()))->toBeTrue();
})->with(['shell.php.jpg', 'page.html', 'no-extension']);

it('ignores the client file name for avatars', function (string $clientName): void {
    postJson('/api/v1/uploads/avatar', ['avatar' => jpegNamed($clientName)])
        ->assertOk();

    $media = $this->user->fresh()->getFirstMedia('avatar');

    expect($media->file_name)->toMatch(ULID_FILE_NAME)
        ->and(file_exists($media->getPath()))->toBeTrue();
})->with(['shell.php.jpg', 'page.html', 'no-extension']);

it('rejects non-image content for ad images', function (UploadedFile $file): void {
    $ad = $this->makeAd($this->user, ['status' => AdStatus::DRAFT->value]);

    postJson("/api/v1/ads/{$ad->id}/images", ['images' => [$file]])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');

    expect($ad->fresh()->getMedia('images'))->toBeEmpty();
})->with([
    'html disguised as jpg' => fn () => htmlDisguisedAsImage(),
    'svg' => fn () => svgImage(),
]);

it('rejects non-image content for avatars', function (UploadedFile $file): void {
    postJson('/api/v1/uploads/avatar', ['avatar' => $file])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');

    expect($this->user->fresh()->getMedia('avatar'))->toBeEmpty();
})->with([
    'html disguised as jpg' => fn () => htmlDisguisedAsImage(),
    'svg' => fn () => svgImage(),
]);
