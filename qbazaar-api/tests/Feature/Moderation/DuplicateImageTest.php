<?php

declare(strict_types=1);

use App\Data\Moderation\ModerationResult;
use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\User;
use App\Services\Catalog\CategoryHierarchy;
use App\Services\Media\PerceptualHashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->seller = User::factory()->phoneVerified()->create();
        $this->otherSeller = User::factory()->phoneVerified()->create();
    });

    Sanctum::actingAs($this->seller, ['*']);

    // Both sides share a category: the check only compares ads under the same root category.
    $this->categoryId = Category::query()->whereNotNull('parent_id')->value('id');

    /**
     * Draft owned by $this->seller with text that passes every text rule,
     * so the only moderation signal under test is the image hash.
     */
    $this->makeCleanDraft = function (): Ad {
        return Ad::withoutSyncingToSearch(fn (): Ad => $this->makeAd($this->seller, [
            'status' => AdStatus::DRAFT->value,
            'category_id' => $this->categoryId,
            'title' => 'Comfy reading chair for the living room',
            'description' => 'Very comfortable reading chair in excellent condition. Wood frame and cotton upholstery. Pick up from West Bay.',
        ]));
    };

    /** ACTIVE fixture ad (valid published_at/expires_at) carrying one hashed image. */
    $this->makeActiveAdWithPhash = function (User $owner, string $phash, array $overrides = []): Ad {
        $ad = Ad::withoutSyncingToSearch(fn (): Ad => $this->makeAd($owner, [
            'status' => AdStatus::ACTIVE->value,
            'category_id' => $this->categoryId,
            'published_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            ...$overrides,
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
        $media->forceFill([
            'phash' => $phash,
            'phash_int' => app(PerceptualHashService::class)->toInteger($phash),
        ])->save();
    }

    return $media;
}

/**
 * Publish the draft and return the moderation result stored on it. The sync
 * test queue runs ModerateAdJob inside the request.
 */
function publishAndReadModerationResult(Ad $draft): ModerationResult
{
    postJson("/api/v1/ads/{$draft->id}/publish", ['accepted_terms' => true], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', AdStatus::PENDING->value);

    $result = $draft->fresh()?->moderation_result;
    assert($result instanceof ModerationResult);

    return $result;
}

it('flags duplicate_image when another seller has an active ad with an identical image hash', function (): void {
    $existing = ($this->makeActiveAdWithPhash)($this->otherSeller, 'a1b2c3d4e5f60718');

    $draft = ($this->makeCleanDraft)();
    attachImageWithPhash($draft, 'a1b2c3d4e5f60718');

    $result = publishAndReadModerationResult($draft);

    expect($result->clean)->toBeFalse()
        ->and($result->flags)->toBe(['duplicate_image'])
        ->and($result->details['duplicate_image'])->toBe(['duplicate_ad_ids' => [$existing->id]]);
});

it('does not flag duplicate_image when the matching image belongs to the same seller', function (): void {
    ($this->makeActiveAdWithPhash)($this->seller, 'a1b2c3d4e5f60718');

    $draft = ($this->makeCleanDraft)();
    attachImageWithPhash($draft, 'a1b2c3d4e5f60718');

    expect(publishAndReadModerationResult($draft)->clean)->toBeTrue();
});

it('does not flag duplicate_image when the Hamming distance exceeds the threshold', function (): void {
    // 12 bits apart — above the configured threshold of 8.
    ($this->makeActiveAdWithPhash)($this->otherSeller, '0000000000000000');

    $draft = ($this->makeCleanDraft)();
    attachImageWithPhash($draft, '0000000000000fff');

    expect(publishAndReadModerationResult($draft)->clean)->toBeTrue();
});

it('flags duplicate_image when the Hamming distance equals the threshold', function (): void {
    // Exactly 8 bits apart — the threshold is inclusive.
    ($this->makeActiveAdWithPhash)($this->otherSeller, '0000000000000000');

    $draft = ($this->makeCleanDraft)();
    attachImageWithPhash($draft, '00000000000000ff');

    expect(publishAndReadModerationResult($draft)->flags)->toContain('duplicate_image');
});

it('does not flag duplicate_image when the candidate image has no phash yet', function (): void {
    // ProcessAdImagesJob lag: media exists but phash is still null —
    // the detector must not raise a false duplicate flag.
    ($this->makeActiveAdWithPhash)($this->otherSeller, 'a1b2c3d4e5f60718');

    $draft = ($this->makeCleanDraft)();
    attachImageWithPhash($draft, null);

    expect(publishAndReadModerationResult($draft)->clean)->toBeTrue();
});

it('does not flag duplicate_image when another seller has an active ad with a malformed phash', function (): void {
    // A corrupted DB row (phash = 'ZZZZ') must not throw or produce a false
    // duplicate flag — the detector skips it and the ad passes cleanly.
    ($this->makeActiveAdWithPhash)($this->otherSeller, 'ZZZZ');

    $draft = ($this->makeCleanDraft)();
    attachImageWithPhash($draft, 'a1b2c3d4e5f60718');

    expect(publishAndReadModerationResult($draft)->clean)->toBeTrue();
});

it('flags a near-duplicate whose hash has the top bit set', function (): void {
    $existing = ($this->makeActiveAdWithPhash)($this->otherSeller, 'ffffffffffffff00');

    $draft = ($this->makeCleanDraft)();
    attachImageWithPhash($draft, 'ffffffffffffffff');

    expect(publishAndReadModerationResult($draft)->details['duplicate_image'])->toBe(['duplicate_ad_ids' => [$existing->id]]);
});

it('ignores matches published before the comparison window', function (): void {
    ($this->makeActiveAdWithPhash)($this->otherSeller, 'a1b2c3d4e5f60718', [
        'published_at' => now()->subDays((int) config('moderation.duplicate_images.window_days') + 1),
    ]);

    $draft = ($this->makeCleanDraft)();
    attachImageWithPhash($draft, 'a1b2c3d4e5f60718');

    expect(publishAndReadModerationResult($draft)->clean)->toBeTrue();
});

it('compares only within the same root category unless configured otherwise', function (bool $sameRootOnly, bool $flagged): void {
    config(['moderation.duplicate_images.same_root_category' => $sameRootOnly]);
    $ownRoot = app(CategoryHierarchy::class)->pathTo($this->categoryId)[0];
    $otherLeaf = Category::query()->whereNotNull('parent_id')->get()
        ->first(fn (Category $category): bool => app(CategoryHierarchy::class)->pathTo($category->id)[0] !== $ownRoot);
    assert($otherLeaf instanceof Category);

    ($this->makeActiveAdWithPhash)($this->otherSeller, 'a1b2c3d4e5f60718', ['category_id' => $otherLeaf->id]);

    $draft = ($this->makeCleanDraft)();
    attachImageWithPhash($draft, 'a1b2c3d4e5f60718');

    expect(publishAndReadModerationResult($draft)->clean)->toBe(! $flagged);
})->with([
    'same root only' => [true, false],
    'any category' => [false, true],
]);
