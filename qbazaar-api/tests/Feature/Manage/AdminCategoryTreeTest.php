<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Services\Admin\Catalog\CategoryTreeBuilder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

use Tests\Concerns\CountsPageQueries;

uses(RefreshDatabase::class, CountsPageQueries::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);
    app()->setLocale('ar');

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = categoryTreeStaff('super_admin');
});

function categoryTreeStaff(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * @param array<string, mixed> $attributes
 */
function categoryTreeNode(string $name, ?Category $parent = null, array $attributes = []): Category
{
    return Category::query()->create([
        'parent_id' => $parent?->id,
        'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)),
        'name' => ['ar' => $name, 'en' => $name],
        'order' => 1,
        'is_active' => true,
        ...$attributes,
    ]);
}

function categoryTreeAds(Category $category, int $count): void
{
    $location = Location::query()->firstOrCreate(['slug' => 'tree-doha'], ['name' => ['ar' => 'الدوحة', 'en' => 'Doha'], 'type' => 'city', 'order' => 1]);

    Ad::factory()->count($count)->create([
        'user_id' => User::factory()->create()->id,
        'category_id' => $category->id,
        'location_id' => $location->id,
    ]);
}

/**
 * Seeds $branches top-level categories, each with two children and a grandchild under the first.
 */
function categoryTreeBranches(int $branches): void
{
    for ($i = 1; $i <= $branches; $i++) {
        $root = categoryTreeNode("Branch {$i}", attributes: ['order' => $i, 'custom_fields' => [['key' => "root_{$i}", 'type' => 'text']]]);
        $first = categoryTreeNode("Leaf {$i}a", $root);
        categoryTreeNode("Leaf {$i}b", $root);
        categoryTreeNode("Deep {$i}", $first, ['custom_fields' => [['key' => "deep_{$i}", 'type' => 'number']]]);
        categoryTreeAds($first, 1);
    }
}

it('shows the whole tree in display order, each row indented under its parent', function (): void {
    $vehicles = categoryTreeNode('مركبات', attributes: ['order' => 1, 'custom_fields' => [['key' => 'condition', 'type' => 'select', 'options' => ['new']]]]);
    $cars = categoryTreeNode('سيارات', $vehicles, ['order' => 2, 'custom_fields' => [['key' => 'year', 'type' => 'number']]]);
    $bikes = categoryTreeNode('دراجات', $vehicles, ['order' => 1, 'is_active' => false]);
    $kids = categoryTreeNode('دراجات أطفال', $bikes);
    $homes = categoryTreeNode('عقارات', attributes: ['order' => 2]);
    categoryTreeAds($cars, 3);
    actingAs($this->admin);

    $html = (string) $this->get(route('admin.categories.index'))->assertOk()->getContent();

    $positions = array_map(fn (Category $category): int|false => strpos($html, 'value="' . $category->id . '"'), [$vehicles, $bikes, $kids, $cars, $homes]);
    expect($positions)->not->toContain(false)
        ->and($positions)->toBe(collect($positions)->sort()->values()->all())
        ->and($html)->toContain('padding-inline-start: 3rem')
        ->and($html)->toContain('مخفي (الأب معطّل)')
        ->and($html)->toContain('معطّل')
        ->and($html)->toContain('حقلان')
        ->and($html)->toContain('منها حقل موروث')
        ->and($html)->toContain(route('admin.categories.fields.index', $cars))
        ->and($html)->toContain('action="' . route('admin.categories.status', $bikes) . '"');
});

it('pages through the tree by top-level branch without splitting one', function (): void {
    categoryTreeBranches(CategoryTreeBuilder::ROOTS_PER_PAGE + 1);
    $last = Category::query()->where('name->en', 'Branch ' . (CategoryTreeBuilder::ROOTS_PER_PAGE + 1))->firstOrFail();
    actingAs($this->admin);

    $first = (string) $this->get(route('admin.categories.index'))->assertOk()->getContent();
    $second = (string) $this->get(route('admin.categories.index', ['page' => 2]))->assertOk()->getContent();

    expect($first)->not->toContain('value="' . $last->id . '"')
        ->and($second)->toContain('value="' . $last->id . '"')
        ->and(substr_count($second, 'name="ids[]"'))->toBe(4);
});

it('finds categories by name or slug and names their parents', function (): void {
    $vehicles = categoryTreeNode('مركبات');
    $cars = categoryTreeNode('سيارات', $vehicles, ['slug' => 'cars-search']);
    categoryTreeNode('عقارات');
    actingAs($this->admin);

    $this->get(route('admin.categories.index', ['q' => 'سيارات']))
        ->assertOk()
        ->assertSee('ضمن: مركبات')
        ->assertSee('value="' . $cars->id . '"', false)
        ->assertDontSee('عقارات');

    $this->get(route('admin.categories.index', ['q' => 'cars-sea']))->assertSee('value="' . $cars->id . '"', false);
});

it('opens the tree only to staff who manage categories', function (string $role, bool $allowed): void {
    actingAs(categoryTreeStaff($role));

    $allowed
        ? $this->get(route('admin.categories.index'))->assertOk()
        : $this->get(route('admin.categories.index'))->assertForbidden();
})->with([
    'super admin' => ['super_admin', true],
    'moderator' => ['moderator', true],
    'support' => ['support', false],
]);

it('keeps the tree within its query budget as the tree grows', function (): void {
    actingAs($this->admin);

    categoryTreeBranches(2);
    $few = $this->steadyQueryCount(route('admin.categories.index'));

    categoryTreeBranches(10);
    $many = $this->steadyQueryCount(route('admin.categories.index'));

    // the categories + one grouped count each for ads, children and saved searches
    expect($many)->toBe($few)->toBeLessThanOrEqual(4);
});

it('keeps search results within their query budget as matches grow', function (): void {
    actingAs($this->admin);
    $uri = route('admin.categories.index', ['q' => 'Leaf']);

    categoryTreeBranches(2);
    $few = $this->steadyQueryCount($uri);

    categoryTreeBranches(12);
    $many = $this->steadyQueryCount($uri);

    // the total + the page + the parents' names + one grouped count each for ads, children and saved searches
    expect($many)->toBe($few)->toBeLessThanOrEqual(6);
});

it('drops the JSON editors from the category form and leaves the fields as they were on save', function (): void {
    $fields = [['key' => 'year', 'type' => 'number', 'label' => ['ar' => 'السنة', 'en' => 'Year']]];
    $category = categoryTreeNode('سيارات', attributes: ['custom_fields' => $fields, 'custom_filters' => [['key' => 'year']]]);
    actingAs($this->admin);

    $this->get(route('admin.categories.edit', $category))
        ->assertOk()
        ->assertDontSee('name="custom_fields"', false)
        ->assertDontSee('name="custom_filters"', false)
        ->assertSee(route('admin.categories.fields.index', $category), false)
        ->assertSee('حقول خاصة به');

    $this->put(route('admin.categories.update', $category), [
        'name' => ['ar' => 'سيارات', 'en' => 'Cars'],
        'slug' => $category->slug,
        'order' => 3,
        'is_active' => '1',
        'custom_fields' => '[]',
    ])->assertSessionHasNoErrors();

    expect($category->fresh()?->custom_fields)->toBe($fields)
        ->and($category->fresh()?->order)->toBe(3);
});

it('takes a deactivated category out of the public tree', function (): void {
    $vehicles = categoryTreeNode('مركبات');
    actingAs(categoryTreeStaff('moderator'));

    expect($this->getJson('/api/v1/categories/tree')->json('data.*.id'))->toBe([$vehicles->id]);

    $this->patch(route('admin.categories.status', $vehicles), ['is_active' => '0'])
        ->assertSessionHas('status', 'تم تعطيل التصنيف. لم يعد يظهر للزوار ولا في نماذج النشر.');

    expect($this->getJson('/api/v1/categories/tree')->json('data'))->toBe([]);
});
