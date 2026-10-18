<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Admin\Catalog\DeleteBlockedNotice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);
    app()->setLocale('ar');

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = safeDeleteStaff('super_admin');
});

function safeDeleteStaff(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function safeDeleteCategory(?Category $parent = null, bool $active = true): Category
{
    $slug = 'cat-' . Str::lower(Str::random(8));

    return Category::query()->create([
        'parent_id' => $parent?->id,
        'slug' => $slug,
        'name' => ['ar' => 'تصنيف ' . $slug, 'en' => 'Category ' . $slug],
        'order' => 1,
        'is_active' => $active,
    ]);
}

function safeDeleteLocation(?Location $parent = null, bool $active = true): Location
{
    $slug = 'loc-' . Str::lower(Str::random(8));

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
 * @param array<string, mixed> $attributes
 */
function safeDeleteAd(array $attributes = []): Ad
{
    return Ad::factory()->create([
        'user_id' => User::factory()->create()->id,
        'category_id' => $attributes['category_id'] ?? safeDeleteCategory()->id,
        'location_id' => $attributes['location_id'] ?? safeDeleteLocation()->id,
        ...$attributes,
    ]);
}

function safeDeleteSavedSearch(?string $categoryId, ?string $locationId): void
{
    DB::table('saved_searches')->insert([
        'id' => (string) Str::ulid(),
        'user_id' => User::factory()->create()->id,
        'name' => 'Saved',
        'query_params' => json_encode([]),
        'alerts_enabled' => true,
        'category_id' => $categoryId,
        'location_id' => $locationId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('refuses to delete a category in use, says what uses it and offers to deactivate it', function (): void {
    $category = safeDeleteCategory();
    safeDeleteAd(['category_id' => $category->id]);
    safeDeleteAd(['category_id' => $category->id])->delete();
    safeDeleteCategory($category);
    safeDeleteSavedSearch($category->id, null);
    actingAs($this->admin);

    $this->from(route('admin.categories.edit', $category))
        ->delete(route('admin.categories.destroy', $category))
        ->assertRedirect(route('admin.categories.edit', $category))
        ->assertSessionHas('error', 'لا يمكن حذف التصنيف «' . $category->name['ar'] . '» لأنه مستخدم: إعلانان، تصنيف فرعي واحد، بحث محفوظ واحد. عطّله بدلاً من حذفه.')
        ->assertSessionHas(DeleteBlockedNotice::SESSION_KEY, [
            'title' => 'لم يُحذف التصنيف «' . $category->name['ar'] . '» لأنه ما زال مستخدماً',
            'reasons' => ['إعلانان', 'تصنيف فرعي واحد', 'بحث محفوظ واحد'],
            'deactivate_url' => route('admin.categories.status', $category),
        ]);

    expect(Category::query()->whereKey($category->id)->exists())->toBeTrue()
        ->and(Category::query()->where('parent_id', $category->id)->count())->toBe(1)
        ->and(Activity::query()->where('log_name', AdminAuditLogger::LOG_NAME)->count())->toBe(0);
});

it('shows the refusal on the page it returns to, with a one-click deactivate', function (): void {
    $category = safeDeleteCategory();
    safeDeleteAd(['category_id' => $category->id]);
    actingAs($this->admin);

    $html = (string) $this->followingRedirects()
        ->from(route('admin.categories.index'))
        ->delete(route('admin.categories.destroy', $category))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-qb-delete-blocked')
        ->and($html)->toContain('إعلان واحد')
        ->and($html)->toContain('تعطيله بدلاً من الحذف')
        ->and($html)->toContain('action="' . route('admin.categories.status', $category) . '"');

    $this->patch(route('admin.categories.status', $category), ['is_active' => '0'])->assertSessionHas('status');

    expect($category->fresh()?->is_active)->toBeFalse();
});

it('deletes a category nothing uses', function (): void {
    $category = safeDeleteCategory();
    actingAs($this->admin);

    $this->delete(route('admin.categories.destroy', $category))
        ->assertRedirect(route('admin.categories.index'))
        ->assertSessionHas('status', 'تم حذف التصنيف.');

    expect(Category::query()->whereKey($category->id)->exists())->toBeFalse();
});

it('refuses to delete a location in use by ads, children, saved searches or addresses', function (string $usedBy, string $reason): void {
    $location = safeDeleteLocation();

    match ($usedBy) {
        'ad' => safeDeleteAd(['location_id' => $location->id]),
        'child' => safeDeleteLocation($location),
        'saved search' => safeDeleteSavedSearch(null, $location->id),
        'address' => UserAddress::factory()->create(['location_id' => $location->id]),
    };
    actingAs($this->admin);

    $this->delete(route('admin.locations.destroy', $location))
        ->assertSessionHas('error', fn (string $message): bool => str_contains($message, $reason));

    expect(Location::query()->whereKey($location->id)->exists())->toBeTrue();
})->with([
    'ad' => ['ad', 'إعلان واحد'],
    'child' => ['child', 'موقع فرعي واحد'],
    'saved search' => ['saved search', 'بحث محفوظ واحد'],
    'address' => ['address', 'عنوان مستخدم واحد'],
]);

it('deletes a location nothing uses', function (): void {
    $location = safeDeleteLocation();
    actingAs($this->admin);

    $this->delete(route('admin.locations.destroy', $location))
        ->assertRedirect(route('admin.locations.index'))
        ->assertSessionHas('status', 'تم حذف الموقع.');

    expect(Location::query()->whereKey($location->id)->exists())->toBeFalse();
});

it('offers no deactivate button when the row is already inactive', function (): void {
    $category = safeDeleteCategory(active: false);
    safeDeleteAd(['category_id' => $category->id]);
    actingAs($this->admin);

    $this->delete(route('admin.categories.destroy', $category))
        ->assertSessionHas(DeleteBlockedNotice::SESSION_KEY, fn (array $notice): bool => $notice['deactivate_url'] === null);
});

it('deletes the unused rows of a bulk selection and names the ones it kept', function (string $resource): void {
    $isCategory = $resource === 'categories';
    $free = $isCategory ? safeDeleteCategory() : safeDeleteLocation();
    $used = $isCategory ? safeDeleteCategory() : safeDeleteLocation();
    safeDeleteAd([$isCategory ? 'category_id' : 'location_id' => $used->id]);
    actingAs($this->admin);

    $this->from(route("admin.{$resource}.index"))
        ->post(route("admin.{$resource}.bulk-destroy"), ['ids' => [$free->id, $used->id]])
        ->assertRedirect(route("admin.{$resource}.index"))
        ->assertSessionHas('error', 'حُذف عنصر واحد. وبقيت عناصر ما زالت مستخدمة: «' . $used->name['ar'] . '» (إعلان واحد).');

    expect($free::query()->whereKey($free->id)->exists())->toBeFalse()
        ->and($used::query()->whereKey($used->id)->exists())->toBeTrue();

    $entry = Activity::query()->where('log_name', AdminAuditLogger::LOG_NAME)->sole();
    expect($entry->event)->toBe("admin.{$resource}.bulk-destroy")
        ->and($entry->properties['deleted'])->toBe([$free->id])
        ->and($entry->properties['kept'])->toBe([$used->id]);
})->with(['categories', 'locations']);

it('confirms a bulk delete that kept nothing back', function (): void {
    $rows = [safeDeleteCategory(), safeDeleteCategory(), safeDeleteCategory()];
    actingAs($this->admin);

    $this->post(route('admin.categories.bulk-destroy'), ['ids' => array_map(fn (Category $row): string => $row->id, $rows)])
        ->assertSessionHas('status', 'حُذفت 3 عناصر.');

    expect(Category::query()->count())->toBe(0);
});

it('validates the bulk selection', function (array $ids): void {
    actingAs($this->admin);

    $this->post(route('admin.categories.bulk-destroy'), ['ids' => $ids])->assertSessionHasErrors();
})->with([
    'empty' => [[]],
    'not ulids' => [['1', 'drop table']],
    'too many' => [array_map(fn (): string => (string) Str::ulid(), range(1, 101))],
]);

it('lets only staff with the permission delete, bulk delete or change the status', function (string $role, bool $allowed): void {
    $category = safeDeleteCategory();
    $location = safeDeleteLocation();
    actingAs(safeDeleteStaff($role));

    $responses = [
        $this->patch(route('admin.categories.status', $category), ['is_active' => '0']),
        $this->patch(route('admin.locations.status', $location), ['is_active' => '0']),
        $this->post(route('admin.categories.bulk-destroy'), ['ids' => [(string) Str::ulid()]]),
        $this->post(route('admin.locations.bulk-destroy'), ['ids' => [(string) Str::ulid()]]),
        $this->delete(route('admin.categories.destroy', $category)),
        $this->delete(route('admin.locations.destroy', $location)),
    ];

    foreach ($responses as $response) {
        $allowed ? expect($response->getStatusCode())->toBe(302) : $response->assertForbidden();
    }

    expect(Category::query()->whereKey($category->id)->exists())->toBe(! $allowed)
        ->and(Location::query()->whereKey($location->id)->exists())->toBe(! $allowed);
})->with([
    'super admin' => ['super_admin', true],
    'moderator' => ['moderator', true],
    'support' => ['support', false],
]);

it('validates the status change', function (): void {
    actingAs($this->admin);

    $this->patch(route('admin.categories.status', safeDeleteCategory()), ['is_active' => 'maybe'])->assertSessionHasErrors('is_active');
});

it('takes a deactivated location out of the public tree and of new ads, but keeps it for ads already there', function (): void {
    $doha = safeDeleteLocation();
    $closed = safeDeleteLocation($doha);
    actingAs($this->admin);

    $this->patch(route('admin.locations.status', $closed), ['is_active' => '0'])
        ->assertSessionHas('status', 'تم تعطيل الموقع. لم يعد يظهر عند نشر إعلان جديد.');

    $tree = $this->getJson('/api/v1/locations/qatar')->assertOk()->json('data');
    $root = collect($tree)->firstWhere('id', $doha->id);

    expect($root['children'] ?? null)->toBe([]);

    $this->patch(route('admin.locations.status', $closed), ['is_active' => '1'])->assertSessionHas('status', 'تم تفعيل الموقع.');

    $root = collect($this->getJson('/api/v1/locations/qatar')->assertOk()->json('data'))->firstWhere('id', $doha->id);
    expect(collect($root['children'])->pluck('id')->all())->toBe([$closed->id]);
});

it('moves neither a category nor a location under its own branch', function (string $resource): void {
    $isCategory = $resource === 'categories';
    $root = $isCategory ? safeDeleteCategory() : safeDeleteLocation();
    $child = $isCategory ? safeDeleteCategory($root) : safeDeleteLocation($root);
    $grandchild = $isCategory ? safeDeleteCategory($child) : safeDeleteLocation($child);
    actingAs($this->admin);

    $payload = [
        'name' => $root->name,
        'slug' => $root->slug,
        'order' => 1,
        'is_active' => '1',
        'parent_id' => $grandchild->id,
        ...($isCategory ? [] : ['type' => 'city']),
    ];

    $this->from(route("admin.{$resource}.edit", $root))
        ->put(route("admin.{$resource}.update", $root), $payload)
        ->assertSessionHasErrors(['parent_id' => 'لا يمكن نقل العنصر تحت نفسه أو تحت أحد فروعه.']);

    expect($root->fresh()?->parent_id)->toBeNull();

    $html = (string) $this->get(route("admin.{$resource}.edit", $root))->getContent();
    expect($html)->not->toContain('<option value="' . $grandchild->id . '"')
        ->and($html)->not->toContain('<option value="' . $root->id . '"');
})->with(['categories', 'locations']);
