<?php

declare(strict_types=1);

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

function makePage(array $overrides = []): Page
{
    return Page::query()->create(array_merge([
        'slug' => 'about',
        'title' => ['ar' => 'من نحن', 'en' => 'About us'],
        'body' => ['ar' => 'المحتوى', 'en' => 'The body'],
        'meta_description' => ['ar' => 'وصف', 'en' => 'Meta'],
        'is_published' => true,
        'published_at' => now(),
        'display_order' => 1,
    ], $overrides));
}

it('lists only published pages', function (): void {
    makePage();
    makePage(['slug' => 'draft', 'is_published' => false]);

    getJson('/api/v1/pages')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'about');
});

it('returns a published page by slug', function (): void {
    makePage();

    getJson('/api/v1/pages/about')
        ->assertOk()
        ->assertJsonPath('data.slug', 'about')
        ->assertJsonPath('data.title.en', 'About us');
});

it('returns 404 for an unpublished page', function (): void {
    makePage(['slug' => 'hidden', 'is_published' => false]);

    getJson('/api/v1/pages/hidden')->assertNotFound();
});

it('returns 404 for an unknown page slug', function (): void {
    getJson('/api/v1/pages/missing')->assertNotFound();
});
