<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Bus::fake();

    $this->seedReferenceData();
    $this->seller = User::factory()->create();
});

it('reorders images for an ad', function (): void {
    Sanctum::actingAs($this->seller, ['*']);

    $ad = $this->makeAd($this->seller, ['status' => AdStatus::DRAFT->value]);

    // Attach three images by going through the upload endpoint so the
    // model collection / order_column populate the same way they would in
    // production.
    postJson("/api/v1/ads/{$ad->id}/images", [
        'images' => [
            UploadedFile::fake()->image('a.jpg', 100, 100),
            UploadedFile::fake()->image('b.jpg', 100, 100),
            UploadedFile::fake()->image('c.jpg', 100, 100),
        ],
    ], ['Accept' => 'application/json'])->assertCreated();

    /** @var array<int, int> $ids */
    $ids = Media::query()
        ->where('model_type', Ad::class)
        ->where('model_id', $ad->id)
        ->orderBy('order_column')
        ->pluck('id')
        ->map(fn ($v): int => (int) $v)
        ->all();

    // Reverse the order.
    $reversed = array_reverse($ids);

    postJson("/api/v1/ads/{$ad->id}/images/reorder", [
        'order' => $reversed,
    ], ['Accept' => 'application/json'])->assertNoContent();

    /** @var array<int, int> $afterIds */
    $afterIds = Media::query()
        ->where('model_type', Ad::class)
        ->where('model_id', $ad->id)
        ->orderBy('order_column')
        ->pluck('id')
        ->map(fn ($v): int => (int) $v)
        ->all();

    expect($afterIds)->toBe($reversed);
});
