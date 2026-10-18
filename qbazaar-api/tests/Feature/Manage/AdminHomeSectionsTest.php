<?php

declare(strict_types=1);

use App\Enums\HomeSection;
use App\Enums\HomeSectionMode;
use App\Models\Ad;
use App\Models\HomeSectionItem;
use App\Models\HomeSectionSetting;
use App\Models\User;
use App\Services\Catalog\HomeFeedCache;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CountsPageQueries;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CountsPageQueries::class, CreatesAds::class);

beforeEach(function (): void {
    $this->withoutVite();
    Cache::flush();
    app()->setLocale('ar');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();

    $this->staff = [
        'super_admin' => User::factory()->create()->assignRole('super_admin'),
        'moderator' => User::factory()->create()->assignRole('moderator'),
        'support' => User::factory()->create()->assignRole('support'),
    ];
    $this->admin = $this->staff['super_admin'];
    $this->seller = User::factory()->create();
});

/** An ad picked for the section at the given position. */
function pickedAd(HomeSection $section, int $position, ?Ad $ad = null): HomeSectionItem
{
    $ad ??= Ad::factory()->active()->create();

    return HomeSectionItem::query()->create(['section' => $section->value, 'ad_id' => $ad->id, 'position' => $position]);
}

/**
 * @return list<string> the section's ad ids in their stored order
 */
function pickedOrder(HomeSection $section): array
{
    return HomeSectionItem::query()->where('section', $section->value)->orderBy('position')->pluck('ad_id')->all();
}

it('opens the home sections screen for staff who manage them only', function (string $role, int $status): void {
    actingAs($this->staff[$role]);

    $this->get(route('admin.home-sections.index'))->assertStatus($status);
})->with([
    'super_admin' => ['super_admin', 200],
    'moderator' => ['moderator', 200],
    'support' => ['support', 403],
]);

it('shows each section with its mode, its picks and the automatic rule', function (): void {
    $ad = Ad::factory()->active()->create(['title' => 'Curated iPhone 15']);
    pickedAd(HomeSection::RECOMMENDED, 1, $ad);
    HomeSectionSetting::query()->create(['section' => 'recommended', 'mode' => HomeSectionMode::MANUAL]);
    actingAs($this->admin);

    $this->get(route('admin.home-sections.index'))
        ->assertOk()
        ->assertSee(__('admin.home_sections.sections.recommended.title'))
        ->assertSee(__('admin.home_sections.sections.best_selling.title'))
        ->assertSee(__('admin.home_sections.sections.recommended.auto_rule'))
        ->assertSee('Curated iPhone 15')
        ->assertSee(route('admin.home-sections.items.store', 'best_selling'), false);
});

it('warns when a manual section has no live pick and falls back to the rule', function (): void {
    pickedAd(HomeSection::BEST_SELLING, 1, Ad::factory()->sold()->create(['title' => 'Sold sofa']));
    HomeSectionSetting::query()->create(['section' => 'best_selling', 'mode' => HomeSectionMode::MANUAL]);
    actingAs($this->admin);

    $this->get(route('admin.home-sections.index'))
        ->assertOk()
        ->assertSee('Sold sofa')
        ->assertSee(__('admin.home_sections.fallback_title'))
        ->assertSee(__('admin.home_sections.not_listed'));
});

it('switches a section between automatic and manual mode', function (): void {
    $feedKey = app(HomeFeedCache::class)->key('ar');
    Cache::put($feedKey, ['stale' => true]);
    actingAs($this->staff['moderator']);

    $this->put(route('admin.home-sections.mode', 'recommended'), ['mode' => 'manual'])
        ->assertRedirect(route('admin.home-sections.index') . '#section-recommended')
        ->assertSessionHas('status', __('admin.home_sections.flash.mode'));

    $setting = HomeSectionSetting::query()->findOrFail('recommended');
    expect($setting->mode)->toBe(HomeSectionMode::MANUAL)
        ->and($setting->updated_by)->toBe($this->staff['moderator']->id)
        ->and(Cache::has($feedKey))->toBeFalse()
        ->and(Activity::query()->where('event', 'admin.home-sections.mode')->sole()->properties->get('mode'))->toBe('manual');

    $this->put(route('admin.home-sections.mode', 'recommended'), ['mode' => 'random'])->assertSessionHasErrors('mode');
    $this->put(route('admin.home-sections.mode', 'trending'), ['mode' => 'manual'])->assertNotFound();
});

it('adds an ad by its id or by a pasted link, at the end of the section', function (): void {
    $first = Ad::factory()->active()->create();
    $second = Ad::factory()->active()->create();
    actingAs($this->admin);

    $this->post(route('admin.home-sections.items.store', 'recommended'), ['ad' => $first->id])
        ->assertRedirect()
        ->assertSessionHas('status', __('admin.home_sections.flash.added'));
    $this->post(route('admin.home-sections.items.store', 'recommended'), ['ad' => 'https://qbazaar.qa/ads/' . strtoupper($second->id) . '?ref=share'])
        ->assertSessionHas('status');

    expect(pickedOrder(HomeSection::RECOMMENDED))->toBe([$first->id, $second->id])
        ->and(HomeSectionItem::query()->where('ad_id', $first->id)->value('added_by'))->toBe($this->admin->id)
        ->and(Activity::query()->where('event', 'admin.home-sections.add')->count())->toBe(2);
});

it('refuses ads that are unknown, not live, already picked or over the section size', function (): void {
    config(['qbazaar.home.best_selling_limit' => 1]);
    $live = Ad::factory()->active()->create();
    actingAs($this->admin);

    $this->post(route('admin.home-sections.items.store', 'best_selling'), ['ad' => 'not-an-id'])->assertSessionHasErrors('ad');
    $this->post(route('admin.home-sections.items.store', 'best_selling'), ['ad' => strtolower((string) Str::ulid())])->assertSessionHasErrors('ad');
    $this->post(route('admin.home-sections.items.store', 'best_selling'), ['ad' => Ad::factory()->sold()->create()->id])
        ->assertSessionHas('error', __('errors.ad.not.active'));

    $this->post(route('admin.home-sections.items.store', 'best_selling'), ['ad' => $live->id])->assertSessionHas('status');
    $this->post(route('admin.home-sections.items.store', 'best_selling'), ['ad' => $live->id])->assertSessionHas('error');
    $this->post(route('admin.home-sections.items.store', 'best_selling'), ['ad' => Ad::factory()->active()->create()->id])
        ->assertSessionHas('error', __('errors.home.section.full'));

    expect(pickedOrder(HomeSection::BEST_SELLING))->toBe([$live->id])
        ->and(Activity::query()->where('event', 'admin.home-sections.add')->count())->toBe(1);
});

it('removes a pick and closes the gap', function (): void {
    $a = pickedAd(HomeSection::RECOMMENDED, 1);
    $b = pickedAd(HomeSection::RECOMMENDED, 2);
    $c = pickedAd(HomeSection::RECOMMENDED, 3);
    actingAs($this->admin);

    $this->delete(route('admin.home-sections.items.destroy', ['recommended', $b->id]))
        ->assertRedirect()
        ->assertSessionHas('status', __('admin.home_sections.flash.removed'));

    expect(pickedOrder(HomeSection::RECOMMENDED))->toBe([$a->ad_id, $c->ad_id])
        ->and(HomeSectionItem::query()->whereKey($c->id)->value('position'))->toBe(2);
});

it('never removes a pick through another section', function (): void {
    $item = pickedAd(HomeSection::RECOMMENDED, 1);
    actingAs($this->admin);

    $this->delete(route('admin.home-sections.items.destroy', ['best_selling', $item->id]))->assertNotFound();

    expect(HomeSectionItem::query()->whereKey($item->id)->exists())->toBeTrue();
});

it('reorders the picks by the positions staff type', function (): void {
    $a = pickedAd(HomeSection::BEST_SELLING, 1);
    $b = pickedAd(HomeSection::BEST_SELLING, 2);
    $c = pickedAd(HomeSection::BEST_SELLING, 3);
    $other = pickedAd(HomeSection::RECOMMENDED, 1);
    actingAs($this->admin);

    $this->put(route('admin.home-sections.order', 'best_selling'), ['positions' => [$c->id => 1, $a->id => 2, $b->id => 3, $other->id => 1]])
        ->assertRedirect()
        ->assertSessionHas('status', __('admin.home_sections.flash.reordered'));

    expect(pickedOrder(HomeSection::BEST_SELLING))->toBe([$c->ad_id, $a->ad_id, $b->ad_id])
        ->and(HomeSectionItem::query()->whereKey($other->id)->value('position'))->toBe(1);
});

it('lets only staff who manage home sections change them', function (): void {
    $item = pickedAd(HomeSection::RECOMMENDED, 1);
    actingAs($this->staff['support']);

    $this->put(route('admin.home-sections.mode', 'recommended'), ['mode' => 'manual'])->assertForbidden();
    $this->post(route('admin.home-sections.items.store', 'recommended'), ['ad' => Ad::factory()->active()->create()->id])->assertForbidden();
    $this->delete(route('admin.home-sections.items.destroy', ['recommended', $item->id]))->assertForbidden();
    $this->put(route('admin.home-sections.order', 'recommended'), ['positions' => [$item->id => 1]])->assertForbidden();
});

it('keeps the screen query count flat as picks are added', function (): void {
    actingAs($this->admin);
    $uri = route('admin.home-sections.index');

    pickedAd(HomeSection::RECOMMENDED, 1);
    pickedAd(HomeSection::BEST_SELLING, 1);
    $few = $this->steadyQueryCount($uri);

    foreach (range(2, 10) as $position) {
        pickedAd(HomeSection::RECOMMENDED, $position);
        pickedAd(HomeSection::BEST_SELLING, $position);
    }

    expect($this->steadyQueryCount($uri))->toBe($few)->toBeLessThanOrEqual(10);
});

it('serves the picks from their indexes', function (): void {
    expect(Schema::hasIndex('home_section_items', 'home_section_items_order_idx'))->toBeTrue()
        ->and(Schema::hasIndex('home_section_items', 'home_section_items_section_ad_unique'))->toBeTrue();
});
