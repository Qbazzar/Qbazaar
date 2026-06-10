<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Events\Ads\AdRejected;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

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
        $this->otherSeller = User::factory()->create();
    });

    Sanctum::actingAs($this->seller, ['*']);

    /**
     * Draft owned by $this->seller with text that passes every text rule,
     * so the only moderation signal under test is the image hash.
     */
    $this->makeCleanDraft = function (): Ad {
        return Ad::withoutSyncingToSearch(fn (): Ad => $this->makeAd($this->seller, [
            'status' => AdStatus::DRAFT->value,
            'title' => 'Comfy reading chair for the living room',
            'description' => 'Very comfortable reading chair in excellent condition. Wood frame and cotton upholstery. Pick up from West Bay.',
        ]));
    };

    /** ACTIVE fixture ad (valid published_at/expires_at) carrying one hashed image. */
    $this->makeActiveAdWithPhash = function (User $owner, string $phash): Ad {
        $ad = Ad::withoutSyncingToSearch(fn (): Ad => $this->makeAd($owner, [
            'status' => AdStatus::ACTIVE->value,
            'published_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
        ]));

        attachImageWithPhash($ad, $phash);

        return $ad;
    };
});

/**
 * Create a real 32×32 PNG in a system temp file and return its path.
 * Named distinctly from ProcessAdImagesJobTest::makeTempPng because Pest
 * loads every test file into one process — duplicate global function
 * names would fatal.
 */
function makeDuplicateImagePng(): string
{
    $image = imagecreatetruecolor(32, 32);
    assert($image !== false);
    $colour = imagecolorallocate($image, 200, 80, 40);
    assert($colour !== false);
    imagefill($image, 0, 0, $colour);

    $stub = tempnam(sys_get_temp_dir(), 'dup_test_');
    assert($stub !== false);
    unlink($stub);

    $path = $stub . '.png';
    imagepng($image, $path);
    imagedestroy($image);

    return $path;
}

/**
 * Attach one image to the ad and pin its phash directly — the hash is
 * normally written async by ProcessAdImagesJob; setting it by hand keeps
 * each scenario's Hamming distance exact. A null phash simulates the
 * window where the job has not run yet.
 */
function attachImageWithPhash(Ad $ad, ?string $phash): Media
{
    /** @var Media $media */
    $media = $ad->addMedia(makeDuplicateImagePng())
        ->usingFileName('image.png')
        ->toMediaCollection('images');

    if ($phash !== null) {
        $media->forceFill(['phash' => $phash])->save();
    }

    return $media;
}

it('parks an ad in PENDING when another seller has an active ad with an identical image hash', function (): void {
    Event::fake([AdRejected::class]);

    $existing = ($this->makeActiveAdWithPhash)($this->otherSeller, 'a1b2c3d4e5f60718');

    $draft = ($this->makeCleanDraft)();
    attachImageWithPhash($draft, 'a1b2c3d4e5f60718');

    postJson("/api/v1/ads/{$draft->id}/publish", [], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::PENDING->value);

    expect($draft->fresh()->status)->toBe(AdStatus::PENDING);

    // Flags are not persisted on a column — they ride the AdRejected event
    // into the seller notification + activity log, so assert them there.
    Event::assertDispatched(
        AdRejected::class,
        fn (AdRejected $event): bool => in_array('duplicate_image', $event->result->flags, true)
            && $event->result->details['duplicate_image'] === ['duplicate_ad_ids' => [$existing->id]],
    );
});

it('publishes to ACTIVE when the matching image belongs to the same seller', function (): void {
    ($this->makeActiveAdWithPhash)($this->seller, 'a1b2c3d4e5f60718');

    $draft = ($this->makeCleanDraft)();
    attachImageWithPhash($draft, 'a1b2c3d4e5f60718');

    postJson("/api/v1/ads/{$draft->id}/publish", [], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::ACTIVE->value);

    expect($draft->fresh()->status)->toBe(AdStatus::ACTIVE);
});

it('publishes to ACTIVE when the Hamming distance exceeds the threshold', function (): void {
    // 12 bits apart — above the configured threshold of 8.
    ($this->makeActiveAdWithPhash)($this->otherSeller, '0000000000000000');

    $draft = ($this->makeCleanDraft)();
    attachImageWithPhash($draft, '0000000000000fff');

    postJson("/api/v1/ads/{$draft->id}/publish", [], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::ACTIVE->value);
});

it('parks an ad in PENDING when the Hamming distance equals the threshold', function (): void {
    // Exactly 8 bits apart — the threshold is inclusive.
    ($this->makeActiveAdWithPhash)($this->otherSeller, '0000000000000000');

    $draft = ($this->makeCleanDraft)();
    attachImageWithPhash($draft, '00000000000000ff');

    postJson("/api/v1/ads/{$draft->id}/publish", [], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::PENDING->value);
});

it('publishes to ACTIVE when the candidate image has no phash yet', function (): void {
    // ProcessAdImagesJob lag: media exists but phash is still null —
    // the detector must not raise a false duplicate flag.
    ($this->makeActiveAdWithPhash)($this->otherSeller, 'a1b2c3d4e5f60718');

    $draft = ($this->makeCleanDraft)();
    attachImageWithPhash($draft, null);

    postJson("/api/v1/ads/{$draft->id}/publish", [], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::ACTIVE->value);
});
