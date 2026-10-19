<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CountsPageQueries;

uses(RefreshDatabase::class, CountsPageQueries::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);
    app()->setLocale('ar');

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = fieldsEditorStaff('super_admin');

    $this->vehicles = fieldsEditorCategory('vehicles', null, [
        ['key' => 'condition', 'type' => 'select', 'label' => ['ar' => 'الحالة', 'en' => 'Condition'], 'options' => ['new', 'used'], 'filterable' => true, 'sort_order' => 10],
        ['key' => 'colour', 'type' => 'text', 'label' => ['ar' => 'اللون', 'en' => 'Colour'], 'sort_order' => 20],
    ]);
    $this->cars = fieldsEditorCategory('cars', $this->vehicles, [
        ['key' => 'year', 'type' => 'range', 'label' => ['ar' => 'سنة الصنع', 'en' => 'Year'], 'min' => 1990, 'max' => 2026, 'required' => true, 'sort_order' => 5],
    ]);
    $this->sedans = fieldsEditorCategory('sedans', $this->cars, null);
});

function fieldsEditorStaff(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * @param list<array<string, mixed>>|null $fields
 */
function fieldsEditorCategory(string $slug, ?Category $parent, ?array $fields): Category
{
    return Category::query()->create([
        'parent_id' => $parent?->id,
        'slug' => $slug,
        'name' => ['ar' => 'ت-' . $slug, 'en' => Str::headline($slug)],
        'order' => 1,
        'is_active' => true,
        'custom_fields' => $fields,
    ]);
}

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function fieldPayload(array $overrides = []): array
{
    return [
        'key' => 'fuel',
        'type' => 'select',
        'label' => ['ar' => 'الوقود', 'en' => 'Fuel'],
        'required' => '1',
        'filterable' => '1',
        'show_in_card' => '0',
        'sort_order' => '40',
        'options' => "petrol | بنزين | Petrol\ndiesel | ديزل | Diesel\nelectric",
        ...$overrides,
    ];
}

/**
 * @return list<string>
 */
function publicFieldKeys(string $slug): array
{
    /** @var list<string> */
    return test()->getJson("/api/v1/categories/{$slug}/fields")->assertOk()->json('data.*.key');
}

it('opens the fields editor and its forms for staff who manage categories only', function (string $role, bool $allowed): void {
    actingAs(fieldsEditorStaff($role));

    foreach ([
        route('admin.categories.fields.index', $this->cars),
        route('admin.categories.fields.create', $this->cars),
        route('admin.categories.fields.create', [$this->cars, 'override' => 'condition']),
        route('admin.categories.fields.edit', [$this->cars, 'year']),
    ] as $uri) {
        $allowed ? $this->get($uri)->assertOk() : $this->get($uri)->assertForbidden();
    }
})->with([
    'super admin' => ['super_admin', true],
    'moderator' => ['moderator', true],
    'support' => ['support', false],
]);

it('refuses every field change to support staff and changes nothing', function (): void {
    actingAs(fieldsEditorStaff('support'));
    $before = $this->cars->fresh()?->custom_fields;

    $this->post(route('admin.categories.fields.store', $this->cars), fieldPayload())->assertForbidden();
    $this->put(route('admin.categories.fields.update', [$this->cars, 'year']), fieldPayload(['type' => 'number']))->assertForbidden();
    $this->delete(route('admin.categories.fields.destroy', [$this->cars, 'year']))->assertForbidden();
    $this->post(route('admin.categories.fields.hide', [$this->cars, 'condition']))->assertForbidden();

    expect($this->cars->fresh()?->custom_fields)->toBe($before);
});

it('lists own and inherited fields in display order with where each comes from', function (): void {
    actingAs($this->admin);

    $html = (string) $this->get(route('admin.categories.fields.index', $this->sedans))->assertOk()->getContent();

    expect($html)->toContain('data-field="year"')
        ->and(strpos($html, 'data-field="year"'))->toBeLessThan(strpos($html, 'data-field="condition"'))
        ->and(strpos($html, 'data-field="condition"'))->toBeLessThan(strpos($html, 'data-field="colour"'))
        ->and($html)->toContain('من «ت-cars»')
        ->and($html)->toContain('من «ت-vehicles»')
        ->and($html)->toContain('رقم ضمن نطاق')
        ->and($html)->toContain('href="' . route('admin.categories.edit', $this->vehicles) . '"')
        ->and($html)->toContain(route('admin.categories.fields.create', [$this->sedans, 'override' => 'condition']))
        ->and($html)->toContain('action="' . route('admin.categories.fields.hide', [$this->sedans, 'condition']) . '"');
});

it('adds a field, records it in the audit log and serves it to posting and the filters', function (): void {
    actingAs(fieldsEditorStaff('moderator'));

    $this->post(route('admin.categories.fields.store', $this->cars), fieldPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.categories.fields.index', $this->cars))
        ->assertSessionHas('status', 'تم حفظ الحقل.');

    $stored = collect($this->cars->fresh()?->custom_fields)->firstWhere('key', 'fuel');
    expect($stored)->toMatchArray([
        'type' => 'select',
        'label' => ['ar' => 'الوقود', 'en' => 'Fuel'],
        'required' => true,
        'filterable' => true,
        'show_in_card' => false,
        'sort_order' => 40,
        'options' => ['petrol', 'diesel', 'electric'],
        'option_labels' => ['petrol' => ['ar' => 'بنزين', 'en' => 'Petrol'], 'diesel' => ['ar' => 'ديزل', 'en' => 'Diesel']],
    ]);

    $entry = Activity::query()->where('log_name', AdminAuditLogger::LOG_NAME)->sole();
    expect($entry->event)->toBe('admin.categories.fields.saved')
        ->and($entry->properties['key'])->toBe('fuel')
        ->and($entry->properties['before'])->toBeNull();

    // Inherited by the sub-category, in sort order, with labelled options and a filter.
    expect(publicFieldKeys('sedans'))->toBe(['year', 'condition', 'colour', 'fuel']);

    $fuel = collect($this->getJson('/api/v1/categories/sedans/fields?lang=ar')->json('data'))->firstWhere('key', 'fuel');
    expect($fuel['options_labeled'])->toBe([
        ['value' => 'petrol', 'label' => 'بنزين'],
        ['value' => 'diesel', 'label' => 'ديزل'],
        ['value' => 'electric', 'label' => 'Electric'],
    ])->and($fuel['required'])->toBeTrue()->and($fuel['filterable'])->toBeTrue();

    expect($this->getJson('/api/v1/categories/sedans/filters')->json('data.*.key'))->toBe(['condition', 'fuel']);
});

it('edits an own field under its key, whatever key the form sends', function (): void {
    actingAs($this->admin);

    $this->put(route('admin.categories.fields.update', [$this->cars, 'year']), fieldPayload([
        'key' => 'renamed',
        'type' => 'number',
        'label' => ['ar' => 'السنة', 'en' => 'Model year'],
        'options' => '',
        'min' => '2000',
        'max' => '',
    ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.categories.fields.index', $this->cars));

    $fields = $this->cars->fresh()?->custom_fields ?? [];
    expect(array_column($fields, 'key'))->toBe(['year'])
        ->and($fields[0])->toMatchArray(['type' => 'number', 'label' => ['ar' => 'السنة', 'en' => 'Model year'], 'min' => 2000, 'max' => null, 'options' => null]);
});

it('overrides an inherited field for the subtree and drops the override again', function (): void {
    actingAs($this->admin);

    $form = (string) $this->get(route('admin.categories.fields.create', [$this->cars, 'override' => 'condition']))->assertOk()->getContent();
    expect($form)->toContain('هذا الحقل موروث من «ت-vehicles»')
        ->and($form)->toContain('<input type="hidden" name="key" value="condition">')
        ->and($form)->toContain('new')
        ->and($form)->toContain('value="الحالة"');

    $this->post(route('admin.categories.fields.store', $this->cars), fieldPayload([
        'key' => 'condition',
        'label' => ['ar' => 'حالة السيارة', 'en' => 'Car condition'],
        'options' => "new\nused\naccident",
    ]))->assertSessionHasNoErrors();

    $sedanCondition = collect($this->getJson('/api/v1/categories/sedans/fields')->json('data'))->firstWhere('key', 'condition');
    $vehicleCondition = collect($this->getJson('/api/v1/categories/vehicles/fields')->json('data'))->firstWhere('key', 'condition');
    expect($sedanCondition['options'])->toBe(['new', 'used', 'accident'])
        ->and($vehicleCondition['options'])->toBe(['new', 'used']);

    $this->get(route('admin.categories.fields.index', $this->cars))->assertSee('خاص (يتجاوز الموروث)');

    $this->delete(route('admin.categories.fields.destroy', [$this->cars, 'condition']))->assertSessionHas('status');

    expect(collect($this->getJson('/api/v1/categories/sedans/fields')->json('data'))->firstWhere('key', 'condition')['options'])->toBe(['new', 'used']);
});

it('hides an inherited field for the subtree and shows it again', function (): void {
    actingAs($this->admin);

    $this->post(route('admin.categories.fields.hide', [$this->cars, 'colour']))
        ->assertRedirect(route('admin.categories.fields.index', $this->cars))
        ->assertSessionHas('status', 'تم إخفاء الحقل في هذا التصنيف وفروعه.');

    expect(publicFieldKeys('cars'))->toBe(['year', 'condition'])
        ->and(publicFieldKeys('sedans'))->toBe(['year', 'condition'])
        ->and(publicFieldKeys('vehicles'))->toBe(['condition', 'colour']);

    $this->get(route('admin.categories.fields.index', $this->cars))
        ->assertSee('data-qb-hidden-fields', false)
        ->assertSee('إظهار مجدداً');
    $this->get(route('admin.categories.fields.index', $this->sedans))
        ->assertSee('مخفي في «ت-cars»')
        ->assertDontSee('إظهار مجدداً');

    $this->delete(route('admin.categories.fields.destroy', [$this->cars, 'colour']))->assertSessionHas('status');

    expect(publicFieldKeys('sedans'))->toBe(['year', 'condition', 'colour']);
});

it('answers 404 for a field the action cannot reach', function (string $method, string $route, array $parameters): void {
    actingAs($this->admin);

    $this->{$method}(route($route, [$this->cars, ...$parameters]), $method === 'put' ? fieldPayload() : [])->assertNotFound();
})->with([
    'edit an inherited field' => ['get', 'admin.categories.fields.edit', ['condition']],
    'update an inherited field' => ['put', 'admin.categories.fields.update', ['condition']],
    'remove an inherited field' => ['delete', 'admin.categories.fields.destroy', ['condition']],
    'hide an own field' => ['post', 'admin.categories.fields.hide', ['year']],
    'hide an unknown field' => ['post', 'admin.categories.fields.hide', ['nothing']],
    'override an own field' => ['get', 'admin.categories.fields.create', ['override' => 'year']],
    'override an unknown field' => ['get', 'admin.categories.fields.create', ['override' => 'nothing']],
]);

it('validates the field', function (array $overrides, string $error): void {
    actingAs($this->admin);

    $this->from(route('admin.categories.fields.create', $this->cars))
        ->post(route('admin.categories.fields.store', $this->cars), fieldPayload($overrides))
        ->assertRedirect(route('admin.categories.fields.create', $this->cars))
        ->assertSessionHasErrors($error);

    expect(array_column($this->cars->fresh()?->custom_fields ?? [], 'key'))->toBe(['year']);
})->with([
    'key missing' => [['key' => ''], 'key'],
    'key with capitals' => [['key' => 'Fuel'], 'key'],
    'key starting with a digit' => [['key' => '1fuel'], 'key'],
    'key already declared here' => [['key' => 'year'], 'key'],
    'unknown type' => [['type' => 'date'], 'type'],
    'arabic label missing' => [['label' => ['ar' => '', 'en' => 'Fuel']], 'label.ar'],
    'markup in a label' => [['label' => ['ar' => '<b>الوقود</b>', 'en' => 'Fuel']], 'label.ar'],
    'label too long' => [['label' => ['ar' => str_repeat('ك', 81), 'en' => 'Fuel']], 'label.ar'],
    'list without options' => [['options' => " \n "], 'options'],
    'repeated option' => [['options' => "petrol\nPetrol"], 'options'],
    'option with markup' => [['options' => "petrol\n<script>"], 'options'],
    'option label with markup' => [['options' => 'petrol | <b>بنزين</b>'], 'options'],
    'too many options' => [['options' => implode("\n", array_map(fn (int $i): string => "o{$i}", range(1, 201)))], 'options'],
    'range without bounds' => [['type' => 'range', 'min' => '', 'max' => ''], 'min'],
    'maximum below minimum' => [['type' => 'number', 'min' => '10', 'max' => '5'], 'max'],
    'bound beyond the number limit' => [['type' => 'number', 'min' => '-10000000000'], 'min'],
    'negative sort order' => [['sort_order' => '-1'], 'sort_order'],
]);

it('accepts the key of a hidden or inherited field as a new own field', function (): void {
    actingAs($this->admin);
    $this->post(route('admin.categories.fields.hide', [$this->cars, 'colour']));

    $this->post(route('admin.categories.fields.store', $this->cars), fieldPayload(['key' => 'colour', 'type' => 'text', 'options' => '']))
        ->assertSessionHasNoErrors();

    expect(collect($this->cars->fresh()?->custom_fields)->firstWhere('key', 'colour'))->toMatchArray(['type' => 'text', 'options' => null]);
});

it('keeps the fields editor within its query budget as fields and ancestors grow', function (): void {
    actingAs($this->admin);
    $uri = route('admin.categories.fields.index', $this->sedans);

    $few = $this->steadyQueryCount($uri);

    $many = array_map(fn (int $i): array => ['key' => "extra_{$i}", 'type' => 'select', 'label' => ['ar' => "ح{$i}", 'en' => "F{$i}"], 'options' => ['a', 'b']], range(1, 25));
    $this->vehicles->update(['custom_fields' => [...($this->vehicles->custom_fields ?? []), ...$many]]);
    $this->sedans->update(['custom_fields' => [['key' => 'colour', 'hidden' => true], ...array_map(fn (array $field): array => ['key' => $field['key'] . '_own'] + $field, $many)]]);

    $manyCount = $this->steadyQueryCount($uri);

    // binding + the names of the categories above it
    expect($manyCount)->toBe($few)->toBeLessThanOrEqual(2);
});
