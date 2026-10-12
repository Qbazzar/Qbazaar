<?php

declare(strict_types=1);

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

it('sanitizes a CMS page body when staff save it', function (): void {
    actingAs($this->admin)->post('/admin/pages', [
        'slug' => 'terms',
        'display_order' => 0,
        'is_published' => '1',
        'title_ar' => 'الشروط',
        'body_ar' => '<p onmouseover="steal()">نص</p><script>steal()</script>',
        'body_en' => '<a href="javascript:steal()">Terms</a>',
    ])->assertRedirect(route('admin.pages.index'));

    $page = Page::query()->where('slug', 'terms')->firstOrFail();

    expect($page->body)->toBe(['ar' => '<p>نص</p>', 'en' => '<a>Terms</a>']);

    getJson('/api/v1/pages/terms')
        ->assertOk()
        ->assertDontSee('steal', false);
});

it('sanitizes a help article body when staff update it', function (): void {
    $category = HelpCategory::query()->create([
        'slug' => 'getting-started',
        'name' => ['ar' => 'البداية', 'en' => 'Getting started'],
        'display_order' => 0,
    ]);
    $article = HelpArticle::query()->create([
        'category_id' => $category->id,
        'slug' => 'first-steps',
        'title' => ['ar' => 'خطوات', 'en' => 'Steps'],
        'body' => ['ar' => '<p>قديم</p>', 'en' => ''],
        'display_order' => 0,
        'is_published' => true,
    ]);

    actingAs($this->admin)->put("/admin/help-articles/{$article->id}", [
        'category_id' => $category->id,
        'slug' => 'first-steps',
        'display_order' => 0,
        'title_ar' => 'خطوات',
        'body_ar' => '<p>جديد</p><img src="x" onerror="steal()">',
    ])->assertRedirect(route('admin.help-articles.index'));

    expect($article->refresh()->body)->toBe(['ar' => '<p>جديد</p><img src="x">', 'en' => '']);
});

it('keeps editor formatting and alignment but strips smuggled style when a page is saved', function (): void {
    actingAs($this->admin)->post('/admin/pages', [
        'slug' => 'about',
        'display_order' => 0,
        'is_published' => '1',
        'title_ar' => 'من نحن',
        'body_ar' => '<h2 style="text-align: center">عنوان</h2><p style="text-align: end; position:fixed"><u>نص</u></p>',
        'body_en' => '<blockquote><p style="text-align: justify">Quote</p></blockquote><hr><a href="https://x.qa" target="_blank" rel="noopener noreferrer nofollow" onclick="x()">x</a>',
    ])->assertRedirect(route('admin.pages.index'));

    $page = Page::query()->where('slug', 'about')->firstOrFail();

    expect($page->body)->toBe([
        'ar' => '<h2 style="text-align: center">عنوان</h2><p><u>نص</u></p>',
        'en' => '<blockquote><p style="text-align: justify">Quote</p></blockquote><hr><a href="https://x.qa" target="_blank" rel="noopener noreferrer nofollow">x</a>',
    ]);
});

it('renders the rich editor for both languages on the page and article forms', function (): void {
    $response = actingAs($this->admin)->get('/admin/pages/create')->assertOk();

    $response->assertSee('data-rich-editor', false)
        ->assertSee('data-dir="rtl"', false)
        ->assertSee('data-dir="ltr"', false)
        ->assertSee('name="body_ar"', false)
        ->assertSee('name="body_en"', false);
});
