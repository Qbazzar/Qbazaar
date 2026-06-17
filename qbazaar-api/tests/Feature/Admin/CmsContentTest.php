<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\HelpArticleResource\Pages\CreateHelpArticle;
use App\Filament\Admin\Resources\HelpArticleResource\Pages\EditHelpArticle;
use App\Filament\Admin\Resources\HelpArticleResource\Pages\ListHelpArticles;
use App\Filament\Admin\Resources\HelpCategoryResource\Pages\CreateHelpCategory;
use App\Filament\Admin\Resources\HelpCategoryResource\Pages\ListHelpCategories;
use App\Filament\Admin\Resources\PageResource\Pages\CreatePage;
use App\Filament\Admin\Resources\PageResource\Pages\EditPage;
use App\Filament\Admin\Resources\PageResource\Pages\ListPages;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    actingAs($admin);
});

/* ── CMS Pages ─────────────────────────────────────────────────────────── */

it('creates a CMS page', function (): void {
    Livewire::test(CreatePage::class)
        ->fillForm([
            'slug' => 'about',
            'title' => ['ar' => 'حول', 'en' => 'About'],
            'body' => ['ar' => '<p>عن</p>', 'en' => '<p>About us</p>'],
            'is_published' => true,
            'display_order' => 0,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $page = Page::query()->where('slug', 'about')->first();
    expect($page)->not->toBeNull();
    expect($page->title['en'])->toBe('About');
});

it('edits a CMS page', function (): void {
    $page = Page::create([
        'slug' => 'terms',
        'title' => ['ar' => 'الشروط', 'en' => 'Terms'],
        'body' => ['ar' => '<p>شروط</p>', 'en' => '<p>Terms</p>'],
        'is_published' => true,
        'display_order' => 0,
    ]);

    Livewire::test(EditPage::class, ['record' => $page->getKey()])
        ->fillForm([
            'slug' => 'terms',
            'title' => ['ar' => 'الشروط', 'en' => 'Terms of Service'],
            'body' => ['ar' => '<p>شروط</p>', 'en' => '<p>Updated</p>'],
            'is_published' => false,
            'display_order' => 2,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $page->refresh();
    expect($page->title['en'])->toBe('Terms of Service');
    expect($page->is_published)->toBeFalse();
});

it('deletes a CMS page', function (): void {
    $page = Page::create([
        'slug' => 'temp-page',
        'title' => ['ar' => 'مؤقت', 'en' => 'Temp'],
        'body' => ['ar' => 'x', 'en' => 'x'],
        'is_published' => true,
        'display_order' => 0,
    ]);

    Livewire::test(ListPages::class)
        ->callTableAction('delete', $page);

    expect(Page::query()->whereKey($page->getKey())->exists())->toBeFalse();
});

/* ── Help categories ───────────────────────────────────────────────────── */

it('creates a help category', function (): void {
    Livewire::test(CreateHelpCategory::class)
        ->fillForm([
            'slug' => 'getting-started',
            'name' => ['ar' => 'البداية', 'en' => 'Getting Started'],
            'display_order' => 0,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(HelpCategory::query()->where('slug', 'getting-started')->exists())->toBeTrue();
});

it('deletes a help category', function (): void {
    $category = HelpCategory::create([
        'slug' => 'faq',
        'name' => ['ar' => 'أسئلة', 'en' => 'FAQ'],
        'display_order' => 0,
    ]);

    Livewire::test(ListHelpCategories::class)
        ->callTableAction('delete', $category);

    expect(HelpCategory::query()->whereKey($category->getKey())->exists())->toBeFalse();
});

/* ── Help articles ─────────────────────────────────────────────────────── */

it('creates a help article', function (): void {
    $category = HelpCategory::create([
        'slug' => 'guides',
        'name' => ['ar' => 'أدلة', 'en' => 'Guides'],
        'display_order' => 0,
    ]);

    Livewire::test(CreateHelpArticle::class)
        ->fillForm([
            'category_id' => $category->getKey(),
            'slug' => 'how-to-post',
            'title' => ['ar' => 'كيف', 'en' => 'How to post'],
            'body' => ['ar' => '<p>محتوى</p>', 'en' => '<p>Body</p>'],
            'is_published' => true,
            'display_order' => 0,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $article = HelpArticle::query()->where('slug', 'how-to-post')->first();
    expect($article)->not->toBeNull();
    expect($article->category_id)->toBe($category->getKey());
});

it('edits a help article', function (): void {
    $category = HelpCategory::create([
        'slug' => 'guides2',
        'name' => ['ar' => 'أدلة', 'en' => 'Guides'],
        'display_order' => 0,
    ]);

    $article = HelpArticle::create([
        'category_id' => $category->getKey(),
        'slug' => 'edit-me',
        'title' => ['ar' => 'عنوان', 'en' => 'Title'],
        'body' => ['ar' => '<p>x</p>', 'en' => '<p>x</p>'],
        'is_published' => true,
        'display_order' => 0,
    ]);

    Livewire::test(EditHelpArticle::class, ['record' => $article->getKey()])
        ->fillForm([
            'category_id' => $category->getKey(),
            'slug' => 'edit-me',
            'title' => ['ar' => 'عنوان', 'en' => 'New Title'],
            'body' => ['ar' => '<p>x</p>', 'en' => '<p>y</p>'],
            'is_published' => false,
            'display_order' => 1,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $article->refresh();
    expect($article->title['en'])->toBe('New Title');
    expect($article->is_published)->toBeFalse();
});

it('deletes a help article', function (): void {
    $category = HelpCategory::create([
        'slug' => 'guides3',
        'name' => ['ar' => 'أدلة', 'en' => 'Guides'],
        'display_order' => 0,
    ]);

    $article = HelpArticle::create([
        'category_id' => $category->getKey(),
        'slug' => 'delete-me',
        'title' => ['ar' => 'عنوان', 'en' => 'Title'],
        'body' => ['ar' => '<p>x</p>', 'en' => '<p>x</p>'],
        'is_published' => true,
        'display_order' => 0,
    ]);

    Livewire::test(ListHelpArticles::class)
        ->callTableAction('delete', $article);

    expect(HelpArticle::query()->whereKey($article->getKey())->exists())->toBeFalse();
});
