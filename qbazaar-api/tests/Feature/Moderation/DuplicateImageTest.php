<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Events\Ads\AdSubmittedForReview;
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

// Publish now always parks the ad in PENDING for manual admin review, so the
// duplicate-image signal rides the AdSubmittedForReview event's
// ModerationResult (flags + details) rather than the resulting status.

it('flags duplicate_image when another seller has an active ad with an identical image hash', function (): void {
    Event::fake([AdSubmittedForReview::class]);

    $existing = ($this->makeActiveAdWithPhash)($this->otherSeller, 'a1b2c3d4e5f60718');

    $draft = ($this->makeCleanDraft)();
    attachImageWithPhash($draft, 'a1b2c3d4e5f60718');

    postJson("/api/v1/ads/{$draft->id}/publish", [], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::PENDING->value);

    Event::assertDispatched(
        AdSubmittedForReview::class,
        fn (AdSubmittedForReview $e): bool => in_array('duplicate_image', $e->result->flags, true)
            && $e->result->details['duplicate_image'] === ['duplicate_ad_ids' => [$existing->id]],
    );
});

it('does not flag duplicate_image when the matching image belongs to the same seller', function (): void {
    Event::fake([AdSubmittedForReview::class]);

    ($this->makeActiveAdWithPhash)($this->seller, 'a1b2c3d4e5f60718');

    $draft = ($this->makeCleanDraft)();
    attachImageWithPhash($draft, 'a1b2c3d4e5f60718');

    postJson("/api/v1/ads/{$draft->id}/publish", [], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::PENDING->value);

    Event::assertDispatched(
        AdSubmittedForReview::class,
        fn (AdSubmittedForReview $e): bool => $e->result->clean,
    );
});

it('does not flag duplicate_image when the Hamming distance exceeds the threshold', function (): void {
    Event::fake([AdSubmittedForReview::class]);

    // 12 bits apart — above the configured threshold of 8.
    ($this->makeActiveAdWithPhash)($this->otherSeller, '0000000000000000');

    $draft = ($this->makeCleanDraft)();
    attachImageWithPhash($draft, '0000000000000fff');

    postJson("/api/v1/ads/{$draft->id}/publish", [], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::PENDING->value);

    Event::assertDispatched(
        AdSubmittedForReview::class,
        fn (AdSubmittedForReview $e): bool => $e->result->clean,
    );
});

it('flags duplicate_image when the Hamming distance equals the threshold', function (): void {
    Event::fake([AdSubmittedForReview::class]);

    // Exactly 8 bits apart — the threshold is inclusive.
    ($this->makeActiveAdWithPhash)($this->otherSeller, '0000000000000000');

    $draft = ($this->makeCleanDraft)();
    attachImageWithPhash($draft, '00000000000000ff');

    postJson("/api/v1/ads/{$draft->id}/publish", [], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::PENDING->value);

    Event::assertDispatched(
        AdSubmittedForReview::class,
        fn (AdSubmittedForReview $e): bool => in_array('duplicate_image', $e->result->flags, true),
    );
});

it('does not flag duplicate_image when the candidate image has no phash yet', function (): void {
    Event::fake([AdSubmittedForReview::class]);

    // ProcessAdImagesJob lag: media exists but phash is still null —
    // the detector must not raise a false duplicate flag.
    ($this->makeActiveAdWithPhash)($this->otherSeller, 'a1b2c3d4e5f60718');

    $draft = ($this->makeCleanDraft)();
    attachImageWithPhash($draft, null);

    postJson("/api/v1/ads/{$draft->id}/publish", [], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::PENDING->value);

    Event::assertDispatched(
        AdSubmittedForReview::class,
        fn (AdSubmittedForReview $e): bool => $e->result->clean,
    );
});

it('does not flag duplicate_image when another seller has an active ad with a malformed phash', function (): void {
    Event::fake([AdSubmittedForReview::class]);

    // A corrupted DB row (phash = 'ZZZZ') must not throw or produce a false
    // duplicate flag — the detector skips it and the ad passes cleanly.
    ($this->makeActiveAdWithPhash)($this->otherSeller, 'ZZZZ');

    $draft = ($this->makeCleanDraft)();
    attachImageWithPhash($draft, 'a1b2c3d4e5f60718');

    postJson("/api/v1/ads/{$draft->id}/publish", [], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::PENDING->value);

    Event::assertDispatched(
        AdSubmittedForReview::class,
        fn (AdSubmittedForReview $e): bool => $e->result->clean,
    );
});
