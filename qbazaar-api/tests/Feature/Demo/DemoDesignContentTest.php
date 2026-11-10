<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdPromotion;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Favorite;
use App\Models\Location;
use App\Models\Offer;
use App\Models\Order;
use App\Models\PurchaseRequest;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Demo\Content\DemoContentRefresher;
use Database\Seeders\Demo\Content\DemoHomeCurator;
use Database\Seeders\Demo\Content\DesignManifest;
use Database\Seeders\Demo\Content\DesignPersonaApplier;
use Database\Seeders\Demo\Content\RefreshOptions;
use Database\Seeders\Demo\DemoMedia;
use Database\Seeders\Demo\DemoOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;

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
    Bus::fake();
    Queue::fake();
    $engine = Mockery::mock(Engine::class);
    $engine->shouldNotReceive('update');
    $engine->shouldNotReceive('delete');
    app(EngineManager::class)->extend('meilisearch', fn () => $engine);
    config(['scout.driver' => 'meilisearch']);

    demoArtisan('demo:refresh-content', ['--dry-run' => true])
        ->expectsOutputToContain('Old placeholder ads removed')
        ->assertSuccessful();

    $dry = app(DemoContentRefresher::class)->run(new RefreshOptions(dryRun: true, curateHome: true));

    Bus::assertNothingDispatched();
    Queue::assertNothingPushed();

    expect(contentFingerprint())->toBe($before)
        ->and(User::query()->findOrFail($demoSeller->id)->full_name)->toBe('Stale name')
        ->and($dry->placeholderAdsRemoved)->toBeGreaterThanOrEqual(1);

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

function demoOwner(string $local = 'extra'): User
{
    return User::factory()->create(['email' => $local . '@' . DemoOptions::EMAIL_DOMAIN, 'language' => 'en']);
}

it('keeps a demo ad that anything else depends on, and deletes one only a demo user favourited', function (): void {
    $owner = demoOwner();
    $real = User::factory()->create(['email' => 'real@example.com']);
    $ad = fn (): Ad => Ad::factory()->active()->create(['user_id' => $owner->id, 'title' => 'Legacy ' . Str::random(8)]);

    $cases = [
        'orders' => fn (Ad $a) => Order::factory()->create(['ad_id' => $a->id, 'seller_id' => $owner->id]),
        'offers' => fn (Ad $a) => Offer::factory()->create(['ad_id' => $a->id, 'seller_id' => $owner->id]),
        'purchase_requests' => fn (Ad $a) => PurchaseRequest::factory()->create(['ad_id' => $a->id, 'seller_id' => $owner->id]),
        'reviews' => fn (Ad $a) => DB::table('reviews')->insert(['id' => strtolower((string) Str::ulid()), 'ad_id' => $a->id, 'seller_id' => $owner->id, 'reviewer_id' => $real->id, 'rating' => 5, 'created_at' => now(), 'updated_at' => now()]),
        'ad_promotions' => fn (Ad $a) => AdPromotion::factory()->create(['ad_id' => $a->id, 'user_id' => $owner->id]),
        'reports' => fn (Ad $a) => Report::factory()->create(['reporter_id' => $real->id, 'target_type' => 'ad', 'target_id' => $a->id]),
        'favorites' => fn (Ad $a) => Favorite::factory()->create(['ad_id' => $a->id, 'user_id' => $real->id]),
        'conversations' => fn (Ad $a) => Conversation::factory()->create(['ad_id' => $a->id, 'seller_id' => $owner->id]),
        'home_section_items' => fn (Ad $a) => DB::table('home_section_items')->insert(['section' => 'best_selling', 'ad_id' => $a->id, 'position' => 1, 'created_at' => now(), 'updated_at' => now()]),
    ];
    $column = ['reports' => 'target_id'];
    $ads = [];

    foreach ($cases as $table => $attach) {
        $ads[$table] = $ad();
        $attach($ads[$table]);
    }

    $demoFavourite = $ad();
    Favorite::factory()->create(['ad_id' => $demoFavourite->id, 'user_id' => $owner->id]);

    app(DemoContentRefresher::class)->run(new RefreshOptions);

    foreach ($ads as $table => $kept) {
        expect(Ad::query()->whereKey($kept->id)->exists())->toBeTrue("Ad with a {$table} row was deleted.")
            ->and(DB::table($table)->where($column[$table] ?? 'ad_id', $kept->id)->exists())->toBeTrue("The {$table} row is gone.");
    }

    expect(Ad::withTrashed()->whereKey($demoFavourite->id)->exists())->toBeFalse()
        ->and(DB::table('favorites')->where('ad_id', $demoFavourite->id)->exists())->toBeFalse();
});

it('checks again under the row lock and keeps an ad that got a chat after it was read', function (): void {
    $owner = demoOwner();
    $ad = Ad::factory()->active()->create(['user_id' => $owner->id, 'title' => 'Legacy racing ad']);
    $reads = 0;

    Ad::retrieved(function (Ad $read) use ($ad, &$reads): void {
        if ($read->id === $ad->id && ++$reads === 2) {
            Conversation::factory()->create(['ad_id' => $ad->id, 'seller_id' => $ad->user_id]);
        }
    });

    $report = app(DemoContentRefresher::class)->run(new RefreshOptions);

    expect($reads)->toBeGreaterThanOrEqual(2)
        ->and(Ad::query()->whereKey($ad->id)->exists())->toBeTrue()
        ->and(Conversation::query()->where('ad_id', $ad->id)->exists())->toBeTrue()
        ->and($report->placeholderAdsRemoved)->toBe(0);
});

it('rolls back the unit that fails and leaves the other ads alone', function (): void {
    $owner = demoOwner();
    $ads = Ad::factory()->active()->count(2)->create(['user_id' => $owner->id]);
    $ads->each(fn (Ad $ad) => Favorite::factory()->create(['ad_id' => $ad->id, 'user_id' => $owner->id]));
    $deletions = 0;

    Ad::deleting(function () use (&$deletions): void {
        if (++$deletions === 2) {
            throw new RuntimeException('Storage went away.');
        }
    });

    expect(fn () => app(DemoContentRefresher::class)->run(new RefreshOptions))->toThrow(RuntimeException::class);

    $survivors = Ad::query()->whereIn('id', $ads->modelKeys())->get();

    expect($survivors)->toHaveCount(1)
        ->and(DB::table('favorites')->where('ad_id', $survivors->modelKeys()[0])->exists())->toBeTrue();
});

it('never puts an ad of a real user on the home page, even with a design title', function (): void {
    User::factory()->create(['email' => 'super-admin@' . DemoOptions::EMAIL_DOMAIN]);
    $featured = (new DesignManifest)->ads()[0];
    $stranger = User::factory()->create(['email' => 'someone@example.com']);
    $theirs = Ad::factory()->active()->create(['user_id' => $stranger->id, 'title' => $featured['title']['en']]);
    $ours = Ad::factory()->active()->create(['user_id' => demoOwner('curated')->id, 'title' => $featured['title']['en']]);

    $result = app(DemoHomeCurator::class)->curate(false);

    expect($result['ads'])->toBe(1)
        ->and(DB::table('home_section_items')->pluck('ad_id')->all())->toBe([$ours->id])
        ->and(DB::table('home_section_items')->where('ad_id', $theirs->id)->exists())->toBeFalse();
});

it('reports a broken manifest', function (): void {
    $directory = sys_get_temp_dir() . '/qb-manifest-' . Str::random(8);
    mkdir($directory);
    $data = json_decode((string) file_get_contents(DesignManifest::DEFAULT_PATH), true, 512, JSON_THROW_ON_ERROR);
    $data['ads'][0]['title']['ar'] = '';
    $data['ads'][1]['images'] = ['no-such-image'];
    file_put_contents($directory . '/manifest.json', json_encode($data, JSON_UNESCAPED_UNICODE));

    $problems = (new DesignManifest($directory . '/manifest.json'))->problems();
    unlink($directory . '/manifest.json');
    rmdir($directory);

    expect(implode("\n", $problems))->toContain('Image file missing')
        ->toContain('unknown image [no-such-image]')
        ->toContain('misses its title in one language');
});

it('leaves the e-mail, password and language of a demo account alone when it takes a design identity', function (): void {
    $user = User::factory()->create([
        'email' => 'buyer@' . DemoOptions::EMAIL_DOMAIN,
        'language' => 'en',
        'password' => Hash::make('keep-this-password'),
    ]);
    $before = $user->only(['email', 'password', 'language']);

    app(DesignPersonaApplier::class)->apply($user, (new DesignManifest)->personas()[0]);
    $after = User::query()->findOrFail($user->id);

    expect($after->only(['email', 'password', 'language']))->toBe($before)
        ->and($after->full_name)->toBe('Farah Alzinati');
});
