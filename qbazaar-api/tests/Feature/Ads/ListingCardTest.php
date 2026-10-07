<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Favorite;
use App\Models\RecentView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Laravel\Scout\EngineManager;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    Cache::flush();
    $this->seller = User::factory()->create();
});

function carAd(User $seller, array $overrides = []): Ad
{
    return Ad::factory()->active()->create([
        'user_id' => $seller->id,
        'category_id' => Category::query()->where('slug', 'cars')->value('id'),
        'condition' => 'used',
        'description' => 'Single owner, full service history.',
        'custom_fields' => [
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2020,
            'mileage_km' => 85000,
            'transmission' => 'automatic',
            'fuel_type' => 'petrol',
        ],
        ...$overrides,
    ]);
}

/**
 * Answers every search with the given ads, in that order. Switch back to the
 * collection driver before saving ads, or indexing reaches the mock.
 *
 * @param list<string> $ids
 */
function searchFinds(array $ids): void
{
    config(['scout.driver' => 'meilisearch']);
    $index = Mockery::mock(Indexes::class);
    $index->shouldReceive('rawSearch')->andReturn([
        'hits' => array_map(fn (string $id): array => ['id' => $id], $ids),
        'totalHits' => count($ids),
        'facetDistribution' => [],
    ]);
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('index')->andReturn($index);
    app()->instance(Client::class, $client);
    app(EngineManager::class)->forgetDrivers();
}

/**
 * The list endpoints and where their first card sits in the response.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function listingEndpoints(Ad $ad): array
{
    return [
        'home' => ['/api/v1/home', 'data.recommended.0'],
        'category page' => ['/api/v1/categories/vehicles', 'data.sections.0.ads.0'],
        'ids lookup' => ["/api/v1/ads?ids={$ad->id}", 'data.0'],
        'feed' => ['/api/v1/ads', 'data.0'],
        'search' => ['/api/v1/search', 'data.0'],
    ];
}

it('adds a summary line and localised chips to the cards of every list endpoint', function (string $locale, array $expectedChips): void {
    $ad = carAd($this->seller);
    searchFinds([$ad->id]);

    foreach (listingEndpoints($ad) as $endpoint => [$url, $path]) {
        $card = getJson($url, ['Accept-Language' => $locale])->assertOk()->json($path);

        expect($card['summary'])->toBe('Single owner, full service history.', $endpoint)
            ->and($card['spec_chips'])->toBe($expectedChips, $endpoint);
    }
})->with([
    'english' => ['en', [
        ['key' => 'condition', 'label' => 'Condition', 'value' => 'Used'],
        ['key' => 'year', 'label' => 'Year', 'value' => '2020'],
        ['key' => 'mileage_km', 'label' => 'Mileage (km)', 'value' => '85000'],
        ['key' => 'transmission', 'label' => 'Transmission', 'value' => 'Automatic'],
    ]],
    'arabic' => ['ar', [
        ['key' => 'condition', 'label' => 'الحالة', 'value' => 'مستعمل'],
        ['key' => 'year', 'label' => 'سنة الصنع', 'value' => '2020'],
        ['key' => 'mileage_km', 'label' => 'الكيلومترات', 'value' => '85000'],
        ['key' => 'transmission', 'label' => 'ناقل الحركة', 'value' => 'أوتوماتيك'],
    ]],
]);

it('builds the cached home feed of every language in one pass', function (): void {
    carAd($this->seller);

    $arabic = getJson('/api/v1/home', ['Accept-Language' => 'ar'])->json('data.recommended.0.spec_chips.0.label');

    DB::enableQueryLog();
    $english = getJson('/api/v1/home', ['Accept-Language' => 'en'])->json('data.recommended.0.spec_chips.0.label');

    expect([$arabic, $english])->toBe(['الحالة', 'Condition'])
        ->and(DB::getQueryLog())->toBeEmpty();
});

it('builds the cached category page of every language in one pass', function (): void {
    carAd($this->seller);
    $page = function (string $locale): array {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $page = getJson('/api/v1/categories/vehicles', ['Accept-Language' => $locale])->assertOk()->json('data');
        DB::disableQueryLog();

        return [$page['sections'][0]['ads'][0]['spec_chips'][0]['label'], count(DB::getQueryLog())];
    };

    [$english, $coldQueries] = $page('en');
    [$arabic, $otherLanguageQueries] = $page('ar');
    [, $warmQueries] = $page('ar');

    expect([$english, $arabic])->toBe(['Condition', 'الحالة'])
        ->and($otherLanguageQueries)->toBe($warmQueries)
        ->and($coldQueries)->toBeGreaterThan($warmQueries);
});

it('shows booleans, selects and skips missing values and unflagged fields', function (): void {
    $apartments = Category::query()->where('slug', 'apartments-for-rent')->firstOrFail();
    $fields = $apartments->custom_fields;
    $fields[3]['show_in_card'] = true;
    $apartments->update(['custom_fields' => $fields]);

    $ad = Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'category_id' => $apartments->id,
        'condition' => null,
        'custom_fields' => ['bedrooms' => 3, 'furnished' => 'semi_furnished', 'parking' => true],
    ]);

    $chips = getJson("/api/v1/ads?ids={$ad->id}", ['Accept-Language' => 'en'])->json('data.0.spec_chips');

    expect($chips)->toBe([
        ['key' => 'bedrooms', 'label' => 'Bedrooms', 'value' => '3'],
        ['key' => 'furnished', 'label' => 'Furnished', 'value' => 'Semi-furnished'],
        ['key' => 'parking', 'label' => 'Parking', 'value' => 'Yes'],
    ]);
});

it('shows the ad condition once even when the category has a condition field', function (): void {
    $phones = Category::query()->where('slug', 'mobile-phones')->firstOrFail();
    $fields = collect($phones->custom_fields)
        ->map(fn (array $field): array => $field['key'] === 'condition' ? [...$field, 'show_in_card' => true] : $field)
        ->all();
    $phones->update(['custom_fields' => $fields]);

    $ad = Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'category_id' => $phones->id,
        'condition' => 'like_new',
        'custom_fields' => ['brand' => 'OnePlus', 'storage_gb' => '256', 'condition' => 'good'],
    ]);

    $chips = getJson("/api/v1/ads?ids={$ad->id}", ['Accept-Language' => 'ar'])->json('data.0.spec_chips');

    expect($chips)->toBe([
        ['key' => 'condition', 'label' => 'الحالة', 'value' => 'شبه جديد'],
        ['key' => 'brand', 'label' => 'الماركة', 'value' => 'OnePlus'],
        ['key' => 'storage_gb', 'label' => 'السعة (جيجا)', 'value' => '256'],
    ]);
});

it('returns an empty chip list when there is nothing to show', function (): void {
    $ad = Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'category_id' => Category::query()->where('slug', 'cars')->value('id'),
        'condition' => null,
        'custom_fields' => null,
    ]);

    expect(getJson("/api/v1/ads?ids={$ad->id}")->json('data.0.spec_chips'))->toBe([]);
});

it('limits the chips to the configured maximum', function (): void {
    config(['qbazaar.cards.max_spec_chips' => 2]);
    $ad = carAd($this->seller);

    $chips = getJson("/api/v1/ads?ids={$ad->id}")->json('data.0.spec_chips');

    expect(array_column($chips, 'key'))->toBe(['condition', 'year']);
});

it('trims the description to one line cut at a word', function (): void {
    $ad = carAd($this->seller, [
        'description' => "Clean car,\n\n  garage kept.   " . str_repeat('Very long text about the car. ', 20),
    ]);

    $summary = getJson("/api/v1/ads?ids={$ad->id}")->json('data.0.summary');

    expect($summary)->toStartWith('Clean car, garage kept. Very long text')
        ->and($summary)->toEndWith('…')
        ->and(mb_strlen($summary))->toBeLessThanOrEqual(121)
        ->and(Str::squish($ad->description))->toStartWith(mb_substr($summary, 0, -1) . ' ');
});

it('keeps angle brackets in the summary as the seller wrote them', function (string $description): void {
    $ad = carAd($this->seller, ['description' => $description]);

    expect(getJson("/api/v1/ads?ids={$ad->id}")->json('data.0.summary'))->toBe($description);
})->with([
    'battery <80%, so priced low.',
    '<3 years old, one owner.',
]);

it('cuts a summary without spaces at the length limit', function (): void {
    config(['qbazaar.cards.summary_length' => 10]);
    $ad = carAd($this->seller, ['description' => str_repeat('ب', 30)]);

    expect(getJson("/api/v1/ads?ids={$ad->id}")->json('data.0.summary'))->toBe(str_repeat('ب', 10) . '…');
});

it('shortens long chip values and leaves values with markup off the card', function (): void {
    config(['qbazaar.cards.spec_chip_max_length' => 4]);
    $cars = Category::query()->where('slug', 'cars')->firstOrFail();
    $cars->update(['custom_fields' => collect($cars->custom_fields)
        ->map(fn (array $field): array => $field['key'] === 'model' ? [...$field, 'show_in_card' => true] : $field)
        ->all()]);
    $ad = carAd($this->seller, ['condition' => null, 'custom_fields' => [
        'model' => '<b>Corolla</b>',
        'year' => 2020,
        'mileage_km' => 85000,
    ]]);

    $chips = getJson("/api/v1/ads?ids={$ad->id}")->json('data.0.spec_chips');

    expect(array_column($chips, 'value', 'key'))->toBe(['year' => '2020', 'mileage_km' => '8500…']);
});

it('adds the summary and chips to the signed-in lists and the chat ad too', function (): void {
    $ad = carAd($this->seller, ['featured' => true]);
    $neighbour = carAd($this->seller);
    $viewer = User::factory()->phoneVerified()->create();
    Favorite::query()->create(['user_id' => $viewer->id, 'ad_id' => $ad->id, 'created_at' => now()]);
    RecentView::query()->create(['user_id' => $viewer->id, 'ad_id' => $ad->id, 'viewed_at' => now()]);
    // Signed in, the account language would win over Accept-Language.
    $english = '?lang=en';

    Sanctum::actingAs($viewer, ['*']);
    $started = postJson("/api/v1/conversations{$english}", ['ad_id' => $ad->id])->assertCreated()->json('data.ad');
    $conversationId = Conversation::query()->value('id');
    $cards = [
        'chat start' => $started,
        'chat' => getJson("/api/v1/conversations/{$conversationId}{$english}")->assertOk()->json('data.ad'),
        'favorites' => getJson("/api/v1/account/favorites{$english}")->assertOk()->json('data.0'),
        'recently viewed' => getJson("/api/v1/account/recently-viewed{$english}")->assertOk()->json('data.0'),
        'seller ads' => getJson("/api/v1/users/{$this->seller->id}/ads{$english}")->assertOk()->json('data.0'),
        'featured' => getJson("/api/v1/ads/featured{$english}")->assertOk()->json('data.0'),
        'similar' => getJson("/api/v1/ads/{$neighbour->id}/similar{$english}")->assertOk()->json('data.0'),
    ];
    Sanctum::actingAs($this->seller, ['*']);
    $cards['own ads'] = getJson("/api/v1/account/ads{$english}")->assertOk()->json('data.0');

    foreach ($cards as $list => $card) {
        expect($card['summary'])->toBe('Single owner, full service history.', $list)
            ->and($card['spec_chips'])->toBe([
                ['key' => 'condition', 'label' => 'Condition', 'value' => 'Used'],
                ['key' => 'year', 'label' => 'Year', 'value' => '2020'],
                ['key' => 'mileage_km', 'label' => 'Mileage (km)', 'value' => '85000'],
                ['key' => 'transmission', 'label' => 'Transmission', 'value' => 'Automatic'],
            ], $list);
    }

    expect($started['category_slug'])->toBe('cars');
});

it('hides the summary and chips of a saved ad the viewer may no longer open', function (array $change): void {
    $ad = carAd($this->seller);
    $viewer = User::factory()->create();
    Favorite::query()->create(['user_id' => $viewer->id, 'ad_id' => $ad->id, 'created_at' => now()]);
    RecentView::query()->create(['user_id' => $viewer->id, 'ad_id' => $ad->id, 'viewed_at' => now()]);
    $ad->forceFill($change)->save();

    Sanctum::actingAs($viewer, ['*']);
    $favorite = getJson('/api/v1/account/favorites')->assertOk()->json('data.0');
    $recent = getJson('/api/v1/account/recently-viewed')->assertOk()->json('data.0');
    Sanctum::actingAs($this->seller, ['*']);
    $own = getJson('/api/v1/account/ads')->assertOk()->json('data.0');

    expect([$favorite['summary'], $favorite['spec_chips'], $recent['summary'], $recent['spec_chips']])->toBe(['', [], '', []])
        ->and($own['summary'])->toBe($ad->description)
        ->and($own['spec_chips'])->not->toBeEmpty();
})->with([
    'back in review' => [['status' => AdStatus::PENDING, 'description' => 'Edited and not reviewed yet.']],
    'blocked' => [['status' => AdStatus::BLOCKED]],
    'seller suspended' => [['seller_active' => false]],
]);

it('keeps the summary of a sold ad, which stays public', function (): void {
    $ad = carAd($this->seller);
    $viewer = User::factory()->create();
    Favorite::query()->create(['user_id' => $viewer->id, 'ad_id' => $ad->id, 'created_at' => now()]);
    $ad->forceFill(['status' => AdStatus::SOLD])->save();

    Sanctum::actingAs($viewer, ['*']);

    expect(getJson('/api/v1/account/favorites')->json('data.0.summary'))->toBe('Single owner, full service history.');
});

it('leaves search hits out once they are no longer listed', function (): void {
    $listed = carAd($this->seller);
    $inReview = carAd($this->seller, ['status' => AdStatus::PENDING]);
    searchFinds([$inReview->id, $listed->id]);

    expect(getJson('/api/v1/search')->assertOk()->json('data.*.id'))->toBe([$listed->id]);
});

it('renders the cards of a list endpoint with a constant number of queries', function (string $endpoint): void {
    // Returns the queries one cold request ran and how many cards it rendered.
    $measure = function () use ($endpoint): array {
        $ids = Ad::query()->orderBy('id')->pluck('id')->all();
        searchFinds($ids);
        [$url, $cards] = match ($endpoint) {
            'home' => ['/api/v1/home', 'data.recommended'],
            'category page' => ['/api/v1/categories/vehicles', 'data.sections.*.ads.*'],
            'ids lookup' => ['/api/v1/ads?ids=' . implode(',', $ids), 'data'],
            'feed' => ['/api/v1/ads', 'data'],
            'search' => ['/api/v1/search', 'data'],
        };

        Cache::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = getJson($url)->assertOk();
        DB::disableQueryLog();
        config(['scout.driver' => 'collection']);

        return [count(DB::getQueryLog()), count($response->json($cards))];
    };

    $shop = User::factory()->business()->create();
    carAd($shop);
    $measure(); // the first run also fills the in-memory hierarchy and counter memos
    [$fewQueries, $fewCards] = $measure();

    $categories = Category::query()->whereIn('slug', ['cars', 'motorcycles', 'apartments-for-rent', 'mobile-phones'])->pluck('id');
    foreach ($categories as $categoryId) {
        carAd(User::factory()->business()->create(), ['category_id' => $categoryId]);
        carAd($shop, ['category_id' => $categoryId]);
    }
    [$manyQueries, $manyCards] = $measure();

    expect($manyCards)->toBeGreaterThan($fewCards)
        ->and($manyQueries)->toBe($fewQueries);
})->with(['home', 'category page', 'ids lookup', 'feed', 'search']);
