<?php

declare(strict_types=1);

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

const FOOTER_PAGE_SLUGS = [
    'careers', 'press', 'magazine', 'connect', 'mobile-apps',
    'safety-tips', 'report-vulnerability', 'youth-protection', 'accessibility', 'privacy-settings', 'legal-notice',
    'real-estate-classifieds', 'pro-info', 'pro-packages', 'advertise',
    'popular-searches', 'overview', 'vehicle-appraisal',
];

function runFooterPagesMigration(string $direction = 'up'): void
{
    $migration = require database_path('migrations/2026_10_11_090000_add_footer_cms_pages.php');

    $migration->{$direction}();
}

it('creates every footer page as a published placeholder in both languages', function (): void {
    Page::query()->whereIn('slug', FOOTER_PAGE_SLUGS)->delete();

    runFooterPagesMigration();

    $pages = Page::query()->whereIn('slug', FOOTER_PAGE_SLUGS)->orderBy('display_order')->get();

    expect($pages->pluck('slug')->all())->toBe(FOOTER_PAGE_SLUGS)
        ->and($pages->every(fn (Page $page): bool => $page->is_published && $page->published_at !== null))->toBeTrue()
        ->and($pages->every(fn (Page $page): bool => $page->title['ar'] !== '' && $page->title['en'] !== ''))->toBeTrue()
        ->and($pages->firstOrFail()->body)->toBe([
            'ar' => '<p>سيتم إضافة محتوى هذه الصفحة قريباً.</p>',
            'en' => '<p>Content for this page is coming soon.</p>',
        ]);
});

it('is idempotent and never overwrites a page the owner edited', function (): void {
    Page::query()->whereIn('slug', FOOTER_PAGE_SLUGS)->delete();
    runFooterPagesMigration();
    Page::query()->where('slug', 'careers')->update([
        'title' => ['ar' => 'انضم إلينا', 'en' => 'Join us'],
        'is_published' => false,
    ]);
    $ids = Page::query()->whereIn('slug', FOOTER_PAGE_SLUGS)->pluck('id', 'slug')->all();

    runFooterPagesMigration();

    expect(Page::query()->whereIn('slug', FOOTER_PAGE_SLUGS)->count())->toBe(count(FOOTER_PAGE_SLUGS))
        ->and(Page::query()->whereIn('slug', FOOTER_PAGE_SLUGS)->pluck('id', 'slug')->all())->toBe($ids)
        ->and(Page::query()->where('slug', 'careers')->firstOrFail()->title['en'])->toBe('Join us')
        ->and(Page::query()->where('slug', 'careers')->firstOrFail()->is_published)->toBeFalse();
});

it('serves the pages from the public CMS endpoints', function (): void {
    Cache::flush();

    foreach (FOOTER_PAGE_SLUGS as $slug) {
        getJson("/api/v1/pages/{$slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $slug)
            ->assertJsonPath('data.body.en', '<p>Content for this page is coming soon.</p>');
    }

    $listed = array_column(getJson('/api/v1/pages')->assertOk()->json('data'), 'slug');

    expect(array_values(array_intersect($listed, FOOTER_PAGE_SLUGS)))->toBe(FOOTER_PAGE_SLUGS);
});

it('does not clash with the seeded pages and refreshes the cached list', function (): void {
    Cache::put('pages.list', [], 3600);
    Page::query()->whereIn('slug', FOOTER_PAGE_SLUGS)->delete();

    runFooterPagesMigration();

    expect(Cache::has('pages.list'))->toBeFalse()
        ->and(Page::query()->whereIn('slug', FOOTER_PAGE_SLUGS)->min('display_order'))->toBeGreaterThanOrEqual(100);
});

it('removes only untouched placeholders on rollback', function (): void {
    runFooterPagesMigration();
    Page::query()->where('slug', 'press')->update(['body' => ['ar' => 'x', 'en' => 'Real press text']]);

    runFooterPagesMigration('down');

    expect(Page::query()->whereIn('slug', FOOTER_PAGE_SLUGS)->pluck('slug')->all())->toBe(['press']);
});
