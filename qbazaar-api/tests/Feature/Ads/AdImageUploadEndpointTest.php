<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\PlatformSetting;
use App\Jobs\ProcessAdImagesJob;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Bus::fake();

    $this->seedReferenceData();
    $this->seller = User::factory()->create();
});

it('attaches uploaded images and dispatches the post-processing job', function (): void {
    Sanctum::actingAs($this->seller, ['*']);

    $ad = $this->makeAd($this->seller, ['status' => AdStatus::DRAFT->value]);

    $files = [
        UploadedFile::fake()->image('one.jpg', 800, 600),
        UploadedFile::fake()->image('two.jpg', 800, 600),
    ];

    $response = postJson("/api/v1/ads/{$ad->id}/images", [
        'images' => $files,
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertCreated()
        ->assertJson(
            fn ($json) => $json
                ->where('success', true)
                ->has('data.images', 2)
                ->etc(),
        );

    expect($ad->fresh()->getMedia('images'))->toHaveCount(2);

    Bus::assertDispatched(ProcessAdImagesJob::class);
});

it('accepts images up to the 20-image limit across uploads', function (): void {
    Sanctum::actingAs($this->seller, ['*']);
    $ad = $this->makeAd($this->seller, ['status' => AdStatus::DRAFT->value]);
    $this->attachImageRows($ad, 18);

    postJson("/api/v1/ads/{$ad->id}/images", [
        'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
    ])->assertCreated();

    expect($ad->media()->count())->toBe(20);

    postJson("/api/v1/ads/{$ad->id}/images", [
        'images' => [UploadedFile::fake()->image('c.jpg')],
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'UPLOAD_003')
        ->assertJsonPath('error.details', ['existing' => 20, 'incoming' => 1, 'max' => 20]);
});

it('reads the image limit from the platform settings', function (): void {
    Sanctum::actingAs($this->seller, ['*']);
    app(SettingsService::class)->put(PlatformSetting::AD_MAX_IMAGES, 3, $this->seller);

    $ad = $this->makeAd($this->seller, ['status' => AdStatus::DRAFT->value]);
    $this->attachImageRows($ad, 2);

    postJson("/api/v1/ads/{$ad->id}/images", [
        'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'UPLOAD_003');

    expect($ad->media()->count())->toBe(2);
});

it('caps the number of files in a single request', function (): void {
    Sanctum::actingAs($this->seller, ['*']);
    config(['qbazaar.ads.max_images_per_upload' => 2]);
    $ad = $this->makeAd($this->seller, ['status' => AdStatus::DRAFT->value]);

    postJson("/api/v1/ads/{$ad->id}/images", [
        'images' => array_map(fn (int $i) => UploadedFile::fake()->image("{$i}.jpg"), [1, 2, 3]),
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('refuses an upload while another upload to the same ad holds the lock', function (): void {
    Sanctum::actingAs($this->seller, ['*']);
    $ad = $this->makeAd($this->seller, ['status' => AdStatus::DRAFT->value]);

    $lock = Cache::lock("ads:{$ad->id}:images", 60);
    $lock->get();

    Sleep::fake(syncWithCarbon: true);

    try {
        postJson("/api/v1/ads/{$ad->id}/images", [
            'images' => [UploadedFile::fake()->image('a.jpg')],
        ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'REQUEST_IN_PROGRESS');
    } finally {
        $lock->release();
    }

    expect($ad->media()->count())->toBe(0);
});

it('renders the thumbnail during the upload and queues the larger sizes', function (): void {
    Sanctum::actingAs($this->seller, ['*']);
    $ad = $this->makeAd($this->seller, ['status' => AdStatus::DRAFT->value]);

    postJson("/api/v1/ads/{$ad->id}/images", [
        'images' => [UploadedFile::fake()->image('a.jpg', 1200, 900)],
    ])->assertCreated();

    $media = $ad->getFirstMedia('images');

    expect($media->hasGeneratedConversion('thumbnail'))->toBeTrue()
        ->and($media->hasGeneratedConversion('large'))->toBeFalse();

    Bus::assertDispatched(PerformConversionsJob::class);
});
