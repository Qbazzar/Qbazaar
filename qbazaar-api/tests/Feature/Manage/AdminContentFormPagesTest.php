<?php

declare(strict_types=1);

use App\Enums\LocationType;
use App\Enums\ModerationRuleLanguage;
use App\Enums\ModerationRuleType;
use App\Models\Ad;
use App\Models\Category;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\Location;
use App\Models\ModerationRule;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);
    app()->setLocale('ar');

    $this->seed(RolesAndPermissionsSeeder::class);
});

/** Resource => the seeded roles allowed to open its create and edit forms. */
dataset('content forms', [
    'categories' => ['categories', ['super_admin', 'moderator']],
    'locations' => ['locations', ['super_admin', 'moderator']],
    'pages' => ['pages', ['super_admin']],
    'help categories' => ['help-categories', ['super_admin']],
    'help articles' => ['help-articles', ['super_admin']],
    'moderation rules' => ['moderation-rules', ['super_admin', 'moderator']],
]);

function contentFormStaff(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function contentFormRecord(string $resource): Model
{
    $slug = Str::lower(Str::random(10));

    return match ($resource) {
        'categories' => Category::query()->create([
            'slug' => $slug,
            'name' => ['ar' => 'إلكترونيات', 'en' => 'Electronics'],
            'order' => 1,
            'is_active' => true,
        ]),
        'locations' => Location::query()->create([
            'slug' => $slug,
            'name' => ['ar' => 'الدوحة', 'en' => 'Doha'],
            'type' => LocationType::cases()[0]->value,
            'lat' => '25.285447',
            'lng' => '51.531040',
            'order' => 1,
        ]),
        'pages' => Page::query()->create([
            'slug' => $slug,
            'title' => ['ar' => 'من نحن', 'en' => 'About'],
            'body' => ['ar' => '<p>نص الصفحة</p>', 'en' => '<p>Body</p>'],
            'meta_description' => ['ar' => 'وصف', 'en' => 'Description'],
            'display_order' => 2,
            'is_published' => true,
            'published_at' => now()->subDay()->startOfMinute(),
        ]),
        'help-categories' => HelpCategory::query()->create([
            'slug' => $slug,
            'name' => ['ar' => 'الأمان', 'en' => 'Safety'],
            'description' => ['ar' => 'نصائح', 'en' => 'Tips'],
            'display_order' => 1,
        ]),
        'help-articles' => HelpArticle::query()->create([
            'category_id' => contentFormRecord('help-categories')->getKey(),
            'slug' => $slug,
            'title' => ['ar' => 'كيف أبيع بأمان', 'en' => 'Selling safely'],
            'body' => ['ar' => '<p>المحتوى</p>', 'en' => '<p>Content</p>'],
            'excerpt' => ['ar' => 'ملخص', 'en' => 'Summary'],
            'display_order' => 3,
            'is_published' => true,
        ]),
        'moderation-rules' => ModerationRule::query()->create([
            'type' => ModerationRuleType::BANNED_WORD->value,
            'value' => 'كلمة-' . $slug,
            'language' => ModerationRuleLanguage::ANY->value,
            'is_active' => true,
        ]),
    };
}

/**
 * Seeds a record whose side column has $count related rows and returns its edit URL.
 * Pages and rules have no relations, so their "related rows" are siblings in the table.
 */
function contentFormEditWithRelated(string $resource, int $count): string
{
    $record = contentFormRecord($resource);

    match ($resource) {
        'categories', 'locations' => (function () use ($resource, $record, $count): void {
            $isCategory = $resource === 'categories';
            $category = $isCategory ? $record : contentFormRecord('categories');
            $location = $isCategory ? contentFormRecord('locations') : $record;
            $owner = User::factory()->create();

            for ($i = 0; $i < $count; $i++) {
                $child = contentFormRecord($resource);
                $child->update(['parent_id' => $record->getKey()]);
            }
            Ad::factory()->count($count)->create([
                'user_id' => $owner->id,
                'category_id' => $category->getKey(),
                'location_id' => $location->getKey(),
            ]);
        })(),
        'help-categories' => HelpArticle::query()->insert(collect(range(1, $count))->map(fn (int $i): array => [
            'id' => (string) Str::ulid(),
            'category_id' => $record->getKey(),
            'slug' => Str::lower(Str::random(12)),
            'title' => json_encode(['ar' => "مقال {$i}", 'en' => "Article {$i}"]),
            'body' => json_encode(['ar' => '<p>ن</p>', 'en' => '']),
            'display_order' => $i,
            'is_published' => $i % 2 === 0,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all()),
        'help-articles' => collect(range(1, $count - 1))->each(fn (): Model => contentFormRecord('help-categories')),
        default => collect(range(1, $count - 1))->each(fn (): Model => contentFormRecord($resource)),
    };

    return route("admin.{$resource}.edit", $record);
}

/** Queries one GET runs once the caches every admin page shares (permissions, nav counters) are warm. */
function contentFormQueryCount(string $uri): int
{
    test()->get($uri)->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->get($uri)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

/**
 * The name => value pairs a browser would post from the page form, read off the rendered HTML,
 * so a test can resubmit the form exactly as an admin would.
 *
 * @return array<string, mixed>
 */
function contentFormPayload(string $html): array
{
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();

    $pairs = [];
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//form[@data-qb-page]//*[self::input or self::select or self::textarea][@name]') ?: [] as $field) {
        /** @var DOMElement $field */
        $name = $field->getAttribute('name');
        $value = match ($field->tagName) {
            'textarea' => $field->textContent,
            'select' => ($xpath->query('.//option[@selected]', $field)?->item(0) ?? $xpath->query('.//option', $field)?->item(0))?->getAttribute('value'),
            default => in_array($field->getAttribute('type'), ['checkbox', 'radio'], true) && ! $field->hasAttribute('checked')
                ? null
                : $field->getAttribute('value'),
        };

        if ($value !== null) {
            $pairs[] = urlencode($name) . '=' . urlencode($value);
        }
    }

    // parse_str applies PHP's own rules: name[ar] nests and a later field of the same name wins.
    parse_str(implode('&', $pairs), $payload);

    return $payload;
}

it('renders create and edit inside the page shell for every role that may open them', function (string $resource, array $roles): void {
    $record = contentFormRecord($resource);

    foreach ($roles as $role) {
        actingAs(contentFormStaff($role));

        foreach ([
            'create' => [route("admin.{$resource}.create"), route("admin.{$resource}.store")],
            'edit' => [route("admin.{$resource}.edit", $record), route("admin.{$resource}.update", $record)],
        ] as $mode => [$uri, $action]) {
            $html = (string) $this->get($uri)->assertOk()->getContent();
            $header = Str::between($html, '<header data-qb-page-header', '</header>');

            expect(substr_count($html, '<h1'))->toBe(1, "{$role} {$mode}: one h1")
                ->and($html)->toContain('<form data-qb-page id="page-form" method="POST" action="' . $action . '"')
                ->and($html)->toContain('<aside aria-label="معلومات جانبية"')
                ->and($html)->toContain('href="' . route("admin.{$resource}.index") . '"')
                ->and($header)->toContain('aria-current="page"')
                ->and($header)->toContain('type="submit"')
                ->and($header)->toContain('href="' . route("admin.{$resource}.index") . '"')
                ->and($html)->not->toContain('max-w-4xl')
                ->and($html)->not->toContain('max-w-2xl');

            if ($mode === 'edit') {
                expect($html)->toContain('<input type="hidden" name="_method" value="PUT">');
            } else {
                expect($html)->not->toContain('name="_method"');
            }
        }
    }
})->with('content forms');

it('refuses the forms to staff roles without the permission', function (string $resource, array $roles): void {
    $record = contentFormRecord($resource);

    foreach (array_diff(['super_admin', 'moderator', 'support'], $roles) as $role) {
        actingAs(contentFormStaff($role));

        $this->get(route("admin.{$resource}.create"))->assertForbidden();
        $this->get(route("admin.{$resource}.edit", $record))->assertForbidden();
    }
})->with('content forms');

it('resubmits every edit form as rendered, side-column fields included, without changing the record', function (string $resource): void {
    actingAs(contentFormStaff('super_admin'));
    $record = contentFormRecord($resource);
    $before = $record->fresh()?->only($record->getFillable());

    $html = (string) $this->get(route("admin.{$resource}.edit", $record))->assertOk()->getContent();
    $payload = contentFormPayload($html);

    expect($payload['_method'] ?? null)->toBe('PUT');

    $this->post(route("admin.{$resource}.update", $record), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route("admin.{$resource}.index"));

    expect($record->fresh()?->only($record->getFillable()))->toEqual($before);
})->with(['categories', 'locations', 'pages', 'help-categories', 'help-articles', 'moderation-rules']);

it('shows the validation summary linked to each failing field and keeps the input', function (string $resource, array $input, array $anchors, string $kept): void {
    actingAs(contentFormStaff('super_admin'));
    $createUrl = route("admin.{$resource}.create");

    $response = $this->followingRedirects()
        ->from($createUrl)
        ->post(route("admin.{$resource}.store"), $input)
        ->assertOk();
    $html = (string) $response->getContent();
    $alert = Str::between($html, '<div role="alert"', '</ul>');

    expect($alert)->toContain('tabindex="-1" data-qb-autofocus')
        ->and($alert)->toContain('تعذّر الحفظ. راجع الحقول التالية:')
        ->and(strpos($html, '<div role="alert"'))->toBeGreaterThan(strpos($html, '</header>'))
        ->and(strpos($html, '<div role="alert"'))->toBeLessThan(strpos($html, '<aside aria-label="معلومات جانبية"'));

    foreach ($anchors as $anchor) {
        expect($alert)->toContain('href="#' . $anchor . '" data-qb-field-link')
            ->and($html)->toContain('id="' . $anchor . '"');
    }
    $response->assertSee('aria-invalid="true"', false)
        ->assertSee($kept, false);
})->with([
    'categories' => ['categories', ['icon' => 'kept-icon'], ['name-ar', 'name-en', 'slug'], 'value="kept-icon"'],
    'locations' => ['locations', ['lat' => '25.5', 'type' => 'nowhere'], ['name-ar', 'slug', 'type'], 'value="25.5"'],
    'pages' => ['pages', ['title_en' => 'Kept title'], ['slug', 'title_ar', 'body_ar'], 'value="Kept title"'],
    'help categories' => ['help-categories', ['icon' => 'kept-icon'], ['slug', 'name_ar'], 'value="kept-icon"'],
    'help articles' => ['help-articles', ['title_en' => 'Kept title'], ['category_id', 'slug', 'title_ar', 'body_ar'], 'value="Kept title"'],
    'moderation rules' => ['moderation-rules', ['value' => 'kept-word'], ['type'], 'value="kept-word"'],
]);

it('keeps each edit page within its query budget with 1 or 25 related rows', function (string $resource, int $budget): void {
    actingAs(contentFormStaff('super_admin'));

    $withOne = contentFormQueryCount(contentFormEditWithRelated($resource, 1));
    $withMany = contentFormQueryCount(contentFormEditWithRelated($resource, 25));

    expect($withMany)->toBe($withOne)
        ->and($withOne)->toBeLessThanOrEqual($budget);
})->with([
    // binding + one count query + the parent options
    'categories (children + ads)' => ['categories', 3],
    'locations (children + ads)' => ['locations', 3],
    // binding only
    'pages (other pages)' => ['pages', 1],
    // binding + one count query
    'help categories (articles)' => ['help-categories', 2],
    // binding + the category options
    'help articles (category options)' => ['help-articles', 2],
    'moderation rules (other rules)' => ['moderation-rules', 1],
]);

it('keeps each create page within its query budget with 1 or 25 existing rows', function (string $resource, int $budget): void {
    actingAs(contentFormStaff('super_admin'));
    $createUrl = route("admin.{$resource}.create");

    contentFormRecord($resource === 'help-articles' ? 'help-categories' : $resource);
    $withOne = contentFormQueryCount($createUrl);
    collect(range(1, 24))->each(fn (): Model => contentFormRecord($resource === 'help-articles' ? 'help-categories' : $resource));
    $withMany = contentFormQueryCount($createUrl);

    expect($withMany)->toBe($withOne)
        ->and($withOne)->toBeLessThanOrEqual($budget);
})->with([
    // the parent or category options, nothing else
    'categories' => ['categories', 1],
    'locations' => ['locations', 1],
    'pages' => ['pages', 0],
    'help categories' => ['help-categories', 0],
    'help articles' => ['help-articles', 1],
    'moderation rules' => ['moderation-rules', 0],
]);

it('counts the side-card figures in one query', function (string $resource, array $labels): void {
    actingAs(contentFormStaff('super_admin'));
    $uri = contentFormEditWithRelated($resource, 3);

    $this->get($uri)->assertOk();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->get($uri)->assertOk();
    $countQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], '_count"'));
    DB::disableQueryLog();

    expect($countQueries)->toHaveCount(1);
    foreach ($labels as $label => $value) {
        expect((string) $response->getContent())->toMatch('~>' . preg_quote($label, '~') . '</dt>\s*<dd[^>]*>\s*' . $value . '\s*</dd>~u');
    }
})->with([
    'categories' => ['categories', ['التصنيفات الفرعية' => '3', 'الإعلانات المرتبطة' => '3']],
    'locations' => ['locations', ['المواقع الفرعية' => '3', 'الإعلانات المرتبطة' => '3']],
    'help categories' => ['help-categories', ['المقالات' => '3', 'المنشورة منها' => '1']],
]);

it('shows an error state in the side card when its figures fail to load, and still opens the form', function (): void {
    actingAs(contentFormStaff('super_admin'));
    $category = contentFormRecord('help-categories');
    Schema::rename('help_articles', 'help_articles_unavailable');

    $this->get(route('admin.help-categories.edit', $category))
        ->assertOk()
        ->assertSee('تعذّر تحميل هذا القسم.')
        ->assertSee('name="name_ar"', false)
        ->assertDontSee('المنشورة منها');
});

it('puts the delete action in the overflow menu on edit only, outside the page form', function (string $resource): void {
    actingAs(contentFormStaff('super_admin'));
    $record = contentFormRecord($resource);
    $destroy = route("admin.{$resource}.destroy", $record);

    $html = (string) $this->get(route("admin.{$resource}.edit", $record))->assertOk()->getContent();
    // update and destroy share a URL, so the detached form is told apart by its hidden class.
    $detached = strpos($html, 'action="' . $destroy . '" class="hidden"');

    expect($html)->toContain('<details data-qb-menu')
        ->and($detached)->toBeGreaterThan(strpos($html, '</main>'))
        ->and(Str::between(substr($html, $detached), '>', '</form>'))->toContain('<input type="hidden" name="_method" value="DELETE">')
        ->and(Str::before(substr($html, $detached), '>'))->toContain('data-confirm=');

    $this->get(route("admin.{$resource}.create"))
        ->assertOk()
        ->assertDontSee('data-qb-menu', false);
})->with(['categories', 'locations', 'pages', 'help-categories', 'help-articles', 'moderation-rules']);

it('links published content to the public site in a new tab', function (): void {
    actingAs(contentFormStaff('super_admin'));
    config(['qbazaar.web_url' => 'https://qbazaar.test']);
    $page = contentFormRecord('pages');
    $draft = contentFormRecord('pages');
    $draft->update(['is_published' => false]);

    $this->get(route('admin.pages.edit', $page))
        ->assertSee('href="https://qbazaar.test/p/' . $page->getAttribute('slug') . '" target="_blank" rel="noopener"', false)
        ->assertSee(__('admin.shell.opens_new_tab'));
    $this->get(route('admin.pages.edit', $draft))
        ->assertDontSee('https://qbazaar.test/p/', false)
        ->assertSee('مسودة');
});

it('keeps the rich editor on the CMS and help forms and guards unsaved changes there', function (string $resource): void {
    actingAs(contentFormStaff('super_admin'));

    $this->get(route("admin.{$resource}.edit", contentFormRecord($resource)))
        ->assertOk()
        ->assertSee('data-dirty-guard', false)
        ->assertSee('data-rich-editor', false)
        ->assertSee('name="body_ar"', false)
        ->assertSee('name="body_en"', false);
})->with(['pages', 'help-articles']);

it('shows help for the selected moderation rule type without a script', function (): void {
    actingAs(contentFormStaff('moderator'));

    $this->get(route('admin.moderation-rules.create'))
        ->assertOk()
        ->assertSee('class="mx-auto w-full max-w-[96rem] group/rule"', false)
        ->assertSee('group-has-[#type_option[value^=blocked]:checked]/rule:hidden', false)
        ->assertSee('group-has-[#type_option[value^=banned]:checked]/rule:hidden', false)
        ->assertSee('<option value="banned_word"', false);
});
