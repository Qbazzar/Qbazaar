<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Laravel\Scout\Jobs\MakeSearchable;

use function Pest\Laravel\deleteJson;

use Tests\Concerns\CreatesAds;

/**
 * Regression: Ad::toSearchableArray() must not throw "property slug on null"
 * when an ad's category/location relation is missing (orphaned FK). That crash
 * 500-ed the entire /search endpoint and blocked scout:import.
 */
uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    Storage::fake('public');
    Storage::fake('local');
});

it('degrades taxonomy slugs to null instead of throwing', function (): void {
    $ad = $this->makeAd(User::factory()->create());

    // Force the relations to resolve to null, mimicking an orphaned FK.
    $ad->setRelation('category', null);
    $ad->setRelation('location', null);

    $array = $ad->toSearchableArray();

    expect($array['category_slug'])->toBeNull();
    expect($array['location_slug'])->toBeNull();
});

it('includes the taxonomy slugs when the relations are present', function (): void {
    $ad = $this->makeAd(User::factory()->create())->fresh(['category', 'location']);

    $array = $ad->toSearchableArray();

    expect($array['category_slug'])->not->toBeNull();
    expect($array['location_slug'])->not->toBeNull();
});

it('indexes a batch of ads with one media query instead of one per ad', function (): void {
    $seller = User::factory()->create();
    $ads = collect(range(1, 3))->map(fn (): Ad => $this->makeAd($seller, ['status' => 'active']));
    $ads->first()->addMedia(UploadedFile::fake()->image('cover.jpg'))->toMediaCollection('images');

    DB::enableQueryLog();
    $documents = (new Ad)->makeSearchableUsing(Ad::query()->whereKey($ads->pluck('id'))->get())
        ->mapWithKeys(fn (Ad $ad): array => [$ad->id => $ad->toSearchableArray()]);
    $mediaQueries = collect(DB::getQueryLog())->filter(fn (array $entry): bool => str_contains($entry['query'], 'from "media"'));

    expect($mediaQueries)->toHaveCount(1)
        ->and($documents[$ads->first()->id]['has_images'])->toBeTrue()
        ->and($documents[$ads->last()->id]['has_images'])->toBeFalse();
});

it('queues a reindex only when an indexed column changes', function (): void {
    config(['scout.queue' => true]);
    Queue::fake();
    $ad = $this->makeAd(User::factory()->create(), ['status' => 'active']);
    Queue::assertPushed(MakeSearchable::class, 1);

    $ad->forceFill(['expiring_notified_at' => now(), 'featured' => true])->save();
    Queue::assertPushed(MakeSearchable::class, 1);

    $ad->forceFill(['title' => 'A new title for the index'])->save();
    Queue::assertPushed(MakeSearchable::class, 2);
});

it('refreshes the search document when a live ad loses an image', function (): void {
    config(['scout.queue' => true]);
    $seller = User::factory()->create();
    $ad = $this->makeAd($seller, ['status' => 'active']);
    $media = $ad->addMedia(UploadedFile::fake()->image('cover.jpg'))->toMediaCollection('images');
    Queue::fake();

    Sanctum::actingAs($seller, ['*']);
    deleteJson("/api/v1/media/{$media->id}")->assertNoContent();

    Queue::assertPushed(MakeSearchable::class, fn (MakeSearchable $job): bool => $job->models->first()?->is($ad) === true);
});
