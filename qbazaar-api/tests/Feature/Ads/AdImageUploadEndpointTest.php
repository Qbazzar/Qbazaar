<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Jobs\ProcessAdImagesJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

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
