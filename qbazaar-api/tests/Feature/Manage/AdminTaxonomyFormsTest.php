<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Location;
use App\Models\User;
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
});

function taxonomyFormStaff(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function taxonomyFormCategory(string $slug = 'vehicles'): Category
{
    return Category::query()->create([
        'slug' => $slug,
        'name' => ['ar' => 'مركبات', 'en' => 'Vehicles'],
        'order' => 1,
        'is_active' => true,
    ]);
}

function taxonomyFormLocation(string $slug, ?Location $parent = null, bool $active = true): Location
{
    return Location::query()->create([
        'parent_id' => $parent?->id,
        'slug' => $slug,
        'name' => ['ar' => 'موقع ' . $slug, 'en' => 'Location ' . $slug],
        'type' => 'city',
        'order' => 1,
        'is_active' => $active,
    ]);
}

/**
 * @return array<string, mixed>
 */
function validCategoryForm(array $overrides = []): array
{
    return [
        'name' => ['ar' => 'سيارات', 'en' => 'Cars'],
        'slug' => 'cars',
        'order' => 1,
        'is_active' => '1',
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function validLocationForm(array $overrides = []): array
{
    return [
        'name' => ['ar' => 'الوكرة', 'en' => 'Al Wakrah'],
        'slug' => 'al-wakrah',
        'type' => 'city',
        'order' => 1,
        'is_active' => '1',
        ...$overrides,
    ];
}

it('creates and edits categories only for staff who manage categories', function (string $role, bool $allowed): void {
    $category = taxonomyFormCategory();
    actingAs(taxonomyFormStaff($role));

    $create = $this->post(route('admin.categories.store'), validCategoryForm());
    $update = $this->put(route('admin.categories.update', $category), validCategoryForm(['slug' => 'vehicles', 'order' => 7]));

    if ($allowed) {
        $create->assertRedirect(route('admin.categories.index'));
        $update->assertRedirect(route('admin.categories.index'));
        expect(Category::query()->where('slug', 'cars')->exists())->toBeTrue()
            ->and($category->fresh()?->order)->toBe(7);
    } else {
        $create->assertForbidden();
        $update->assertForbidden();
        expect(Category::query()->where('slug', 'cars')->exists())->toBeFalse()
            ->and($category->fresh()?->order)->toBe(1);
    }
})->with([
    'super admin' => ['super_admin', true],
    'moderator' => ['moderator', true],
    'support' => ['support', false],
]);

it('validates the category form', function (array $overrides, string $error): void {
    taxonomyFormCategory('taken');
    actingAs(taxonomyFormStaff('super_admin'));

    $this->post(route('admin.categories.store'), validCategoryForm($overrides))->assertSessionHasErrors($error);

    expect(Category::query()->count())->toBe(1);
})->with([
    'missing Arabic name' => [['name' => ['ar' => '', 'en' => 'Cars']], 'name.ar'],
    'markup in the English name' => [['name' => ['ar' => 'سيارات', 'en' => '<b>Cars</b>']], 'name.en'],
    'slug with spaces' => [['slug' => 'new cars'], 'slug'],
    'slug already used' => [['slug' => 'taken'], 'slug'],
    'unknown parent' => [['parent_id' => '01JUNKNOWNPARENT0000000000'], 'parent_id'],
    'icon with markup' => [['icon' => '<svg>'], 'icon'],
    'negative order' => [['order' => -1], 'order'],
]);

it('creates and edits locations only for staff who manage locations', function (string $role, bool $allowed): void {
    $location = taxonomyFormLocation('doha');
    actingAs(taxonomyFormStaff($role));

    $create = $this->post(route('admin.locations.store'), validLocationForm());
    $update = $this->put(route('admin.locations.update', $location), validLocationForm(['slug' => 'doha', 'is_active' => '0']));

    if ($allowed) {
        $create->assertRedirect(route('admin.locations.index'));
        $update->assertRedirect(route('admin.locations.index'));
        expect(Location::query()->where('slug', 'al-wakrah')->exists())->toBeTrue()
            ->and($location->fresh()?->is_active)->toBeFalse();
    } else {
        $create->assertForbidden();
        $update->assertForbidden();
        expect(Location::query()->where('slug', 'al-wakrah')->exists())->toBeFalse()
            ->and($location->fresh()?->is_active)->toBeTrue();
    }
})->with([
    'super admin' => ['super_admin', true],
    'moderator' => ['moderator', true],
    'support' => ['support', false],
]);

it('validates the location form', function (array $overrides, string $error): void {
    taxonomyFormLocation('taken');
    actingAs(taxonomyFormStaff('super_admin'));

    $this->post(route('admin.locations.store'), validLocationForm($overrides))->assertSessionHasErrors($error);

    expect(Location::query()->count())->toBe(1);
})->with([
    'missing English name' => [['name' => ['ar' => 'الوكرة', 'en' => '']], 'name.en'],
    'markup in the Arabic name' => [['name' => ['ar' => '<i>الوكرة</i>', 'en' => 'Al Wakrah']], 'name.ar'],
    'unknown type' => [['type' => 'planet'], 'type'],
    'slug already used' => [['slug' => 'taken'], 'slug'],
    'latitude out of range' => [['lat' => '91'], 'lat'],
    'longitude out of range' => [['lng' => '-181'], 'lng'],
    'non-boolean status' => [['is_active' => 'maybe'], 'is_active'],
]);

it('filters the location list by status', function (): void {
    taxonomyFormLocation('open-city');
    taxonomyFormLocation('closed-city', active: false);
    actingAs(taxonomyFormStaff('moderator'));

    $this->get(route('admin.locations.index', ['status' => 'inactive']))
        ->assertOk()
        ->assertSee('closed-city')
        ->assertDontSee('open-city');

    $this->get(route('admin.locations.index', ['status' => 'active']))
        ->assertOk()
        ->assertSee('open-city')
        ->assertDontSee('closed-city');
});

it('keeps the location list within its query budget as locations grow', function (): void {
    actingAs(taxonomyFormStaff('super_admin'));
    $seed = function (int $count): void {
        for ($i = 0; $i < $count; $i++) {
            $city = taxonomyFormLocation('city-' . Str::lower(Str::random(8)));
            taxonomyFormLocation('area-' . Str::lower(Str::random(8)), $city, $i % 2 === 0);
        }
    };

    $seed(2);
    $few = $this->steadyQueryCount(route('admin.locations.index'));

    $seed(12);
    $many = $this->steadyQueryCount(route('admin.locations.index'));

    // the total + the page + the parents of the page in one eager load
    expect($many)->toBe($few)->toBeLessThanOrEqual(4);
});
