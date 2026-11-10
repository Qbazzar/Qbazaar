<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Demo\Content\DemoContentRefresher;
use Database\Seeders\Demo\Content\DesignManifest;
use Database\Seeders\Demo\Content\RefreshOptions;
use Database\Seeders\Demo\DemoMedia;
use Database\Seeders\Demo\DemoOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\PendingCommand;

use function Pest\Laravel\artisan;
use function Pest\Laravel\seed;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    config(['queue.default' => 'database']);

    seed(DatabaseSeeder::REFERENCE_SEEDERS);
});

/**
 * @param array<string, mixed> $options
 */
function demoArtisan(string $command, array $options = []): PendingCommand
{
    $pending = artisan($command, $options);
    assert($pending instanceof PendingCommand);

    return $pending;
}

function seedDemo(): void
{
    demoArtisan('qbazaar:demo', ['--users' => 10, '--ads' => 40])->assertSuccessful();
}

/**
 * A cheap fingerprint of everything the refresh is allowed to touch plus
 * everything it must not.
 *
 * @return array<string, string>
 */
function contentFingerprint(): array
{
    return collect(['ads', 'media', 'users', 'business_profiles', 'favorites', 'categories', 'locations'])
        ->mapWithKeys(fn (string $table): array => [$table => md5(DB::table($table)->get()->map(fn (object $row): string => md5((string) json_encode($row)))->sort()->values()->toJson())])
        ->all();
}

it('ships a manifest whose images exist and whose categories and locations map onto ours', function (): void {
    $manifest = new DesignManifest;

    expect($manifest->problems())->toBe([])
        ->and($manifest->ads())->not->toBeEmpty()
        ->and($manifest->personas())->not->toBeEmpty();

    $leaves = Category::query()->whereNotNull('parent_id')->pluck('slug')->all();
    $parents = Category::query()->whereNull('parent_id')->pluck('slug')->all();
    $districts = Location::query()->pluck('slug')->all();

    foreach ($manifest->ads() as $ad) {
        expect($leaves)->toContain($ad['category'])
            ->and($districts)->toContain($manifest->districtSlug($ad['location']));
    }

    foreach ($manifest->categories() as $category) {
        expect($parents)->toContain($category['map']);

        if (isset($category['leaf'])) {
            expect($leaves)->toContain($category['leaf']);
        }
    }

    foreach ($manifest->pooledLeafSlugs() as $leaf) {
        expect($leaves)->toContain($leaf);
    }
});

it('has an Arabic and an English text for every ad and persona', function (): void {
    $manifest = new DesignManifest;

    foreach ($manifest->ads() as $ad) {
        expect($ad['title']['ar'])->toMatch('/\p{Arabic}/u')
            ->and($ad['description']['ar'])->toMatch('/\p{Arabic}/u')
            ->and($ad['title']['en'])->not->toBe('');
    }

    foreach ($manifest->personas() as $persona) {
        expect($persona['name']['ar'])->toMatch('/\p{Arabic}/u');
    }
});

it('builds the demo catalog from the design, and a refresh on top of it adds nothing', function (): void {
    seedDemo();

    $manifest = new DesignManifest;
    $titles = collect($manifest->ads())->flatMap(fn (array $ad): array => [$ad['title']['en'], $ad['title']['ar']])->all();

    expect(Ad::query()->whereIn('title', $titles)->count())->toBe(min(40, count($manifest->ads())));

    $m3 = Ad::query()->whereIn('title', ['BMW M3 Competition xDrive 2022 — 48,000 km, Full History', 'بي إم دبليو M3 كومبتيشن xDrive موديل 2022 — 48,000 كم، سجل صيانة كامل'])->firstOrFail();

    expect((string) $m3->price)->toBe('285000.00')
        ->and($m3->getMedia('images'))->toHaveCount(3);

    $photos = Media::query()->where('collection_name', 'images')->get();

    expect($photos)->not->toBeEmpty()
        ->and($photos->every(fn (Media $media): bool => $media->getCustomProperty(DemoMedia::DESIGN_PROPERTY) !== null))->toBeTrue();

    $buyer = User::query()->where('email', 'buyer@' . DemoOptions::EMAIL_DOMAIN)->firstOrFail();
    expect($buyer->full_name)->toBeIn(['Farah Alzinati', 'فرح الزيناتي'])
        ->and($buyer->getFirstMedia('avatar'))->not->toBeNull();

    $count = fn (): array => [
        'ads' => Ad::query()->count(),
        'media' => Media::query()->count(),
        'users' => User::query()->count(),
        'profiles' => DB::table('business_profiles')->count(),
    ];
    $afterSeed = $count();

    $first = app(DemoContentRefresher::class)->run(new RefreshOptions);
    $second = app(DemoContentRefresher::class)->run(new RefreshOptions);

    $curated = app(DemoContentRefresher::class)->run(new RefreshOptions(curateHome: true));
    $again = app(DemoContentRefresher::class)->run(new RefreshOptions(curateHome: true));

    expect($count())->toBe($afterSeed)
        ->and($curated->homeAdsCurated)->toBeGreaterThan(0)
        ->and($curated->companiesFeatured)->toBeGreaterThan(0)
        ->and($again->homeAdsCurated)->toBe(0)
        ->and($again->companiesFeatured)->toBe(0)
        ->and(DB::table('home_section_items')->where('section', 'best_selling')->count())->toBe($curated->homeAdsCurated)
        ->and($first->designAdsCreated)->toBe(0)
        ->and($second->designAdsCreated)->toBe(0)
        ->and($first->placeholderAdsRemoved)->toBe(0)
        ->and(DB::table('ads')->select('user_id', 'title')->groupBy('user_id', 'title')->havingRaw('COUNT(*) > 1')->get())->toHaveCount(0);
});

it('replaces old placeholder ads of demo users, keeps ads with history and leaves every other row alone', function (): void {
    seedDemo();

    $demoSeller = User::query()->where('email', 'seller@' . DemoOptions::EMAIL_DOMAIN)->firstOrFail();
    $placeholder = Ad::factory()->create(['user_id' => $demoSeller->id, 'title' => 'Old grey placeholder ad']);
    $placeholder->addMedia(UploadedFile::fake()->image('grey.jpg', 40, 40))->toMediaCollection('images');

    $withHistory = Ad::query()->whereHas('conversations')->firstOrFail();
    $withHistory->forceFill(['title' => 'Legacy ad with history'])->saveQuietly();
    $withHistory->clearMediaCollection('images');
    $withHistory->addMedia(UploadedFile::fake()->image('grey.jpg', 40, 40))->toMediaCollection('images');

    $stranger = User::factory()->create(['email' => 'someone@example.com', 'full_name' => 'Real Person']);
    $strangerAd = Ad::factory()->create(['user_id' => $stranger->id, 'title' => 'Generator 100 kVA']);
    $strangerAd->addMedia(UploadedFile::fake()->image('real.jpg', 40, 40))->toMediaCollection('images');

    $strangerBefore = [
        'user' => User::query()->findOrFail($stranger->id)->getAttributes(),
        'ad' => Ad::query()->findOrFail($strangerAd->id)->getAttributes(),
        'media' => $strangerAd->getMedia('images')->map->getKey()->all(),
    ];

    $report = app(DemoContentRefresher::class)->run(new RefreshOptions);

    expect(Ad::query()->whereKey($placeholder->id)->exists())->toBeFalse()
        ->and(Media::query()->where('model_id', $placeholder->id)->exists())->toBeFalse()
        ->and($report->placeholderAdsRemoved)->toBe(1)
        ->and(Ad::query()->whereKey($withHistory->id)->exists())->toBeTrue()
        ->and($report->adsKeptForHistory)->toBeGreaterThanOrEqual(1)
        ->and(User::query()->findOrFail($stranger->id)->getAttributes())->toBe($strangerBefore['user'])
        ->and(Ad::query()->findOrFail($strangerAd->id)->getAttributes())->toBe($strangerBefore['ad'])
        ->and(Ad::query()->findOrFail($strangerAd->id)->getMedia('images')->map->getKey()->all())->toBe($strangerBefore['media']);
});

it('changes nothing in dry-run mode and asks for --force to change content in production', function (): void {
    seedDemo();

    $demoSeller = User::query()->where('email', 'seller@' . DemoOptions::EMAIL_DOMAIN)->firstOrFail();
    Ad::factory()->create(['user_id' => $demoSeller->id, 'title' => 'Old grey placeholder ad']);
    $demoSeller->forceFill(['full_name' => 'Stale name'])->save();

    $before = contentFingerprint();

    demoArtisan('demo:refresh-content', ['--dry-run' => true])
        ->expectsOutputToContain('Old placeholder ads removed')
        ->assertSuccessful();

    expect(contentFingerprint())->toBe($before)
        ->and(User::query()->findOrFail($demoSeller->id)->full_name)->toBe('Stale name');

    app()->detectEnvironment(fn (): string => 'production');

    demoArtisan('demo:refresh-content')->assertFailed();
    expect(contentFingerprint())->toBe($before);
    demoArtisan('demo:refresh-content', ['--dry-run' => true])->assertSuccessful();
});

it('gives demo ads a fresh expiry, revives expired ones and spreads publication, without touching other ads', function (): void {
    $demoOwner = User::factory()->create(['email' => 'seller@' . DemoOptions::EMAIL_DOMAIN]);
    $stranger = User::factory()->create(['email' => 'someone@example.com']);
    $old = now()->subDays(40);

    $make = fn (User $owner, AdStatus $status): Ad => Ad::factory()->create([
        'user_id' => $owner->id,
        'status' => $status,
        'published_at' => $old,
        'expires_at' => now()->subDays(10),
    ]);
    $expired = $make($demoOwner, AdStatus::EXPIRED);
    $live = $make($demoOwner, AdStatus::ACTIVE);
    $draft = Ad::factory()->create(['user_id' => $demoOwner->id, 'status' => AdStatus::DRAFT]);
    $others = $make($stranger, AdStatus::EXPIRED);
    $othersBefore = Ad::query()->findOrFail($others->id)->getAttributes();

    demoArtisan('demo:keep-alive', ['--dry-run' => true])->assertSuccessful();
    expect(Ad::query()->findOrFail($expired->id)->status)->toBe(AdStatus::EXPIRED);

    demoArtisan('demo:keep-alive', ['--days' => 45])->assertSuccessful();

    $expired->refresh();
    $live->refresh();

    expect($expired->status)->toBe(AdStatus::ACTIVE)
        ->and($expired->expires_at?->between(now()->addDays(44), now()->addDays(46)))->toBeTrue()
        ->and($expired->published_at?->gt(now()->subDays(6)))->toBeTrue()
        ->and($live->expires_at?->between(now()->addDays(44), now()->addDays(46)))->toBeTrue()
        ->and($live->published_at?->isSameDay($old))->toBeTrue()
        ->and(Ad::query()->findOrFail($draft->id)->status)->toBe(AdStatus::DRAFT)
        ->and(Ad::query()->findOrFail($others->id)->getAttributes())->toBe($othersBefore);
});

it('can leave expired demo ads expired when refreshing', function (): void {
    $demoOwner = User::factory()->create(['email' => 'seller@' . DemoOptions::EMAIL_DOMAIN, 'language' => 'en']);
    $designTitle = (new DesignManifest)->ads()[0]['title']['en'];
    $expired = Ad::factory()->create(['user_id' => $demoOwner->id, 'title' => $designTitle, 'status' => AdStatus::EXPIRED, 'expires_at' => now()->subDay()]);

    app(DemoContentRefresher::class)->run(new RefreshOptions(reviveExpired: false));

    expect(Ad::query()->findOrFail($expired->id)->status)->toBe(AdStatus::EXPIRED);
});
