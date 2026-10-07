<?php

declare(strict_types=1);

use App\Models\Category;
use App\Services\Ads\CustomFieldsValidator;
use Illuminate\Validation\ValidationException;

/**
 * @param array<string, mixed> $values
 * @return array<string, mixed> the validation errors, empty when the values pass
 */
function customFieldErrors(array $values): array
{
    $category = new Category;
    $category->custom_fields = [
        ['key' => 'mileage_km', 'type' => 'number'],
        ['key' => 'model', 'type' => 'text'],
        ['key' => 'trim', 'type' => 'select'],
    ];

    try {
        app(CustomFieldsValidator::class)->validate($category, $values);
    } catch (ValidationException $invalid) {
        return $invalid->errors();
    }

    return [];
}

it('accepts ordinary values', function (): void {
    expect(customFieldErrors(['mileage_km' => '85000', 'model' => 'Corolla', 'trim' => 'GLI']))->toBe([]);
});

it('refuses numbers beyond the configured bound', function (string $number): void {
    expect(customFieldErrors(['mileage_km' => $number]))->toHaveKey('custom_fields.mileage_km');
})->with([
    'too large' => ['1000000000'],
    'too small' => ['-1000000000'],
    'endless digits' => [str_repeat('9', 5000)],
]);

it('refuses markup and long text in text fields and in selects without options', function (string $key, string $value): void {
    expect(customFieldErrors([$key => $value]))->toHaveKey("custom_fields.{$key}");
})->with([
    'text markup' => ['model', '<b>Corolla</b>'],
    'select markup' => ['trim', 'GLI <script>'],
    'long select text' => ['trim', str_repeat('a', 256)],
]);
