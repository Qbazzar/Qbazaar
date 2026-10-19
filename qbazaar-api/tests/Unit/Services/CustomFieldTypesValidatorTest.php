<?php

declare(strict_types=1);

use App\Models\Category;
use App\Services\Ads\CustomFieldsValidator;
use Illuminate\Validation\ValidationException;

/**
 * @param array<string, mixed> $values
 * @return array<string, mixed> the validation errors, empty when the values pass
 */
function typedFieldErrors(array $values): array
{
    $category = new Category;
    $category->custom_fields = [
        ['key' => 'features', 'type' => 'multiselect', 'options' => ['gps', 'sunroof', 'camera']],
        ['key' => 'year', 'type' => 'range', 'min' => 1990, 'max' => 2026, 'required' => true],
        ['key' => 'seats', 'type' => 'number', 'min' => 1],
        ['key' => 'warranty', 'type' => 'boolean'],
        ['key' => 'colour', 'type' => 'select', 'options' => ['red', 'blue']],
        ['key' => 'hidden_one', 'hidden' => true],
    ];

    try {
        app(CustomFieldsValidator::class)->validate($category, $values);
    } catch (ValidationException $invalid) {
        return $invalid->errors();
    }

    return [];
}

it('accepts values of every field type', function (): void {
    expect(typedFieldErrors([
        'features' => ['gps', 'camera'],
        'year' => '2015',
        'seats' => 5,
        'warranty' => true,
        'colour' => 'red',
    ]))->toBe([]);
});

it('requires a required field', function (): void {
    expect(typedFieldErrors([]))->toHaveKey('custom_fields.year');
});

it('refuses values outside a field type or its bounds', function (array $values, string $error): void {
    expect(typedFieldErrors(['year' => 2000, ...$values]))->toHaveKey($error);
})->with([
    'multiselect as text' => [['features' => 'gps'], 'custom_fields.features'],
    'unknown multiselect option' => [['features' => ['gps', 'jetpack']], 'custom_fields.features.1'],
    'repeated multiselect option' => [['features' => ['gps', 'gps']], 'custom_fields.features.0'],
    'range below its minimum' => [['year' => 1980], 'custom_fields.year'],
    'range above its maximum' => [['year' => 2030], 'custom_fields.year'],
    'number below its minimum' => [['seats' => 0], 'custom_fields.seats'],
    'boolean as text' => [['warranty' => 'maybe'], 'custom_fields.warranty'],
    'unknown select option' => [['colour' => 'green'], 'custom_fields.colour'],
]);

it('keeps values of keys outside the schema as sent', function (): void {
    $category = new Category;
    $category->custom_fields = [['key' => 'year', 'type' => 'number']];

    expect(app(CustomFieldsValidator::class)->validate($category, ['year' => 2001, 'legacy' => 'kept']))
        ->toBe(['year' => 2001, 'legacy' => 'kept']);
});
