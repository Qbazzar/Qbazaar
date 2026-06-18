<?php

declare(strict_types=1);

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

/**
 * @param array<string, mixed> $overrides
 */
function makeHelpCategory(array $overrides = []): HelpCategory
{
    return HelpCategory::query()->create(array_merge([
        'slug' => 'getting-started',
        'name' => ['ar' => 'البداية', 'en' => 'Getting started'],
        'description' => ['ar' => 'وصف', 'en' => 'Description'],
        'icon' => 'heroicon-o-book-open',
        'display_order' => 1,
    ], $overrides));
}

/**
 * @param array<string, mixed> $overrides
 */
function makeHelpArticle(HelpCategory $category, array $overrides = []): HelpArticle
{
    return HelpArticle::query()->create(array_merge([
        'category_id' => $category->id,
        'slug' => 'how-to-post',
        'title' => ['ar' => 'كيف تنشر إعلان', 'en' => 'How to post an ad'],
        'body' => ['ar' => 'المحتوى', 'en' => 'The body'],
        'excerpt' => ['ar' => 'مقتطف', 'en' => 'Excerpt'],
        'is_published' => true,
        'display_order' => 1,
        'views_count' => 0,
    ], $overrides));
}

it('lists help categories with published article counts', function (): void {
    $category = makeHelpCategory();
    makeHelpArticle($category);
    makeHelpArticle($category, ['slug' => 'draft', 'is_published' => false]);

    getJson('/api/v1/help/categories')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'getting-started')
        ->assertJsonPath('data.0.articles_count', 1);
});

it('returns a category with only its published articles', function (): void {
    $category = makeHelpCategory();
    makeHelpArticle($category);
    makeHelpArticle($category, ['slug' => 'hidden', 'is_published' => false]);

    getJson('/api/v1/help/categories/getting-started')
        ->assertOk()
        ->assertJsonPath('data.slug', 'getting-started')
        ->assertJsonCount(1, 'data.articles');
});

it('returns 404 for an unknown help category slug', function (): void {
    getJson('/api/v1/help/categories/nope')->assertNotFound();
});

it('returns a published article and increments its view count', function (): void {
    $category = makeHelpCategory();
    $article = makeHelpArticle($category);

    getJson('/api/v1/help/articles/how-to-post')
        ->assertOk()
        ->assertJsonPath('data.slug', 'how-to-post')
        ->assertJsonPath('data.category.slug', 'getting-started');

    expect($article->refresh()->views_count)->toBe(1);
});

it('hides unpublished articles behind a 404', function (): void {
    $category = makeHelpCategory();
    makeHelpArticle($category, ['slug' => 'secret', 'is_published' => false]);

    getJson('/api/v1/help/articles/secret')->assertNotFound();
});

it('searches published articles by title', function (): void {
    $category = makeHelpCategory();
    makeHelpArticle($category, ['slug' => 'post-ad', 'title' => ['ar' => 'نشر', 'en' => 'How to post an ad']]);

    getJson('/api/v1/help/search?q=post')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'post-ad');
});

it('returns an empty result for a too-short help query', function (): void {
    getJson('/api/v1/help/search?q=a')
        ->assertOk()
        ->assertJsonPath('data', []);
});
