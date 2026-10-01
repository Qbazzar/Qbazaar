<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Events\Ads\AdApproved;
use App\Events\Ads\AdPublished;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\Search\SavedSearchMatchNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    Notification::fake();

    $this->child = Category::query()->whereNotNull('parent_id')->firstOrFail();
    $this->parent = Category::query()->findOrFail($this->child->parent_id);
    $this->location = Location::query()->firstOrFail();

    $this->seller = User::factory()->create();
    $this->ad = $this->makeAd($this->seller, [
        'status' => AdStatus::ACTIVE->value,
        'title' => 'Toyota Land Cruiser 2018',
        'description' => 'Full option, single owner, service history.',
        'category_id' => $this->child->id,
        'location_id' => $this->location->id,
        'price' => 150000,
        'price_type' => 'negotiable',
        'condition' => 'used',
        'custom_fields' => ['make' => 'Toyota', 'year' => '2018'],
        'published_at' => now(),
    ]);
});

/**
 * Saves through the API so the indexed criteria are computed the real way.
 *
 * @param array<string, mixed> $queryParams
 */
function saveSearchFor(User $owner, array $queryParams, bool $alerts = true): SavedSearch
{
    Sanctum::actingAs($owner, ['*']);

    $id = postJson('/api/v1/account/saved-searches', [
        'name' => 'My search',
        'query_params' => $queryParams,
        'alerts_enabled' => $alerts,
    ])->assertCreated()->json('data.id');

    return SavedSearch::query()->findOrFail($id);
}

function publish(Ad $ad): void
{
    AdPublished::dispatch($ad);
}

it('matches the filters with the same meaning as search', function (array $queryParams, bool $expected): void {
    $owner = User::factory()->create();
    $test = $this;
    $resolved = array_map(fn (mixed $value): mixed => $value instanceof Closure ? $value($test) : $value, $queryParams);
    saveSearchFor($owner, $resolved);

    publish($this->ad);

    $expected
        ? Notification::assertSentTo($owner, SavedSearchMatchNotification::class)
        : Notification::assertNotSentTo($owner, SavedSearchMatchNotification::class);
})->with([
    'parent category' => [['category_id' => fn ($t) => $t->parent->id], true],
    'category slug' => [['category_slug' => fn ($t) => $t->child->slug], true],
    'other category' => [['category_id' => fn ($t) => Category::query()->whereKeyNot([$t->child->id, $t->parent->id])->whereNull('parent_id')->value('id')], false],
    'unknown slug' => [['category_slug' => 'no-such-category'], false],
    'location' => [['location_id' => fn ($t) => $t->location->id], true],
    'price in range' => [['price_min' => 100000, 'price_max' => 200000], true],
    'price below min' => [['price_min' => 160000], false],
    'price type' => [['price_type' => 'negotiable'], true],
    'other price type' => [['price_type' => 'fixed'], false],
    'condition' => [['condition' => 'used'], true],
    'other condition' => [['condition' => 'new'], false],
    'keyword words' => [['q' => 'land toyota'], true],
    'keyword miss' => [['q' => 'nissan'], false],
    'custom field equality' => [['custom_fields' => ['make' => 'toyota']], true],
    'custom field mismatch' => [['custom_fields' => ['make' => 'Nissan']], false],
    'custom field range' => [['custom_fields' => ['year' => ['min' => 2015, 'max' => 2020]]], true],
    'custom field out of range' => [['custom_fields' => ['year' => ['min' => 2019]]], false],
    'everything' => [['q' => 'cruiser', 'category_id' => fn ($t) => $t->parent->id, 'price_max' => 150000, 'custom_fields' => ['make' => 'Toyota']], true],
]);

it('stops alerting once alerts are switched off with PATCH', function (): void {
    $owner = User::factory()->create();
    $search = saveSearchFor($owner, ['q' => 'toyota']);

    patchJson("/api/v1/account/saved-searches/{$search->id}", ['alerts_enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.alerts_enabled', false)
        ->assertJsonPath('data.name', 'My search');

    publish($this->ad);
    Notification::assertNotSentTo($owner, SavedSearchMatchNotification::class);

    patchJson("/api/v1/account/saved-searches/{$search->id}", ['alerts_enabled' => true])->assertOk();

    AdApproved::dispatch($this->ad);
    Notification::assertSentTo($owner, SavedSearchMatchNotification::class);
});

it('replaces name and filters with PUT and refreshes the indexed criteria', function (): void {
    $owner = User::factory()->create();
    $search = saveSearchFor($owner, ['q' => 'nissan']);

    putJson("/api/v1/account/saved-searches/{$search->id}", [
        'name' => 'Cruisers',
        'query_params' => ['category_slug' => $this->parent->slug, 'price_max' => 200000],
    ])->assertOk()
        ->assertJsonPath('data.name', 'Cruisers')
        ->assertJsonPath('data.query_params.price_max', 200000)
        ->assertJsonPath('data.alerts_enabled', true);

    $search->refresh();
    expect($search->category_id)->toBe($this->parent->id)
        ->and($search->price_max)->toBe('200000.00');

    publish($this->ad);
    Notification::assertSentTo($owner, SavedSearchMatchNotification::class);
});

it('alerts a user once per ad across several matching searches and repeated events', function (): void {
    $owner = User::factory()->create();
    saveSearchFor($owner, ['q' => 'toyota']);
    saveSearchFor($owner, ['category_id' => $this->parent->id]);

    publish($this->ad);
    AdApproved::dispatch($this->ad);

    Notification::assertSentToTimes($owner, SavedSearchMatchNotification::class, 1);
});

it('skips the seller, users who blocked the seller and users the seller blocked', function (): void {
    $blocker = User::factory()->create();
    $blocked = User::factory()->create();
    saveSearchFor($blocker, ['q' => 'toyota']);
    saveSearchFor($blocked, ['q' => 'toyota']);
    saveSearchFor($this->seller, ['q' => 'toyota']);
    $blocker->blockedUsers()->attach($this->seller->id, ['created_at' => now()]);
    $this->seller->blockedUsers()->attach($blocked->id, ['created_at' => now()]);

    publish($this->ad);

    Notification::assertSentTimes(SavedSearchMatchNotification::class, 0);
});

it('does not alert for ads that are not live', function (): void {
    saveSearchFor(User::factory()->create(), ['q' => 'toyota']);
    $this->ad->forceFill(['status' => AdStatus::PENDING->value])->save();

    publish($this->ad);

    Notification::assertSentTimes(SavedSearchMatchNotification::class, 0);
});

it('keeps saved searches private to their owner', function (): void {
    $search = saveSearchFor(User::factory()->create(), ['q' => 'toyota']);

    Sanctum::actingAs(User::factory()->create(), ['*']);

    patchJson("/api/v1/account/saved-searches/{$search->id}", ['alerts_enabled' => false])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'SEARCH_002');
    putJson("/api/v1/account/saved-searches/{$search->id}", ['name' => 'Mine', 'query_params' => ['q' => 'x']])
        ->assertNotFound();
});

it('validates updates', function (): void {
    $search = saveSearchFor(User::factory()->create(), ['q' => 'toyota']);

    patchJson("/api/v1/account/saved-searches/{$search->id}", [])->assertStatus(422);
    patchJson("/api/v1/account/saved-searches/{$search->id}", ['alerts_enabled' => 'maybe'])->assertStatus(422);
    putJson("/api/v1/account/saved-searches/{$search->id}", ['name' => 'x', 'query_params' => ['condition' => 'broken']])
        ->assertStatus(422);

    $tooManyFields = array_fill_keys(array_map(fn (int $i): string => "field_{$i}", range(1, 21)), 'x');
    putJson("/api/v1/account/saved-searches/{$search->id}", ['name' => 'x', 'query_params' => ['custom_fields' => $tooManyFields]])
        ->assertStatus(422);
});

it('backfills criteria and alert switches for existing saved searches', function (): void {
    $owner = User::factory()->create();
    $id = (string) Str::ulid();
    DB::table('saved_searches')->insert([
        'id' => $id,
        'user_id' => $owner->id,
        'name' => 'Legacy',
        'query_params' => json_encode(['category_slug' => $this->child->slug, 'price_min' => '10', 'condition' => 'used']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_10_01_200500_add_alert_criteria_to_saved_searches_table.php');
    $migration->down();
    $migration->up();

    $row = SavedSearch::query()->findOrFail($id);

    expect($row->alerts_enabled)->toBeTrue()
        ->and($row->category_id)->toBe($this->child->id)
        ->and($row->price_min)->toBe('10.00')
        ->and($row->condition)->toBe('used');
});
