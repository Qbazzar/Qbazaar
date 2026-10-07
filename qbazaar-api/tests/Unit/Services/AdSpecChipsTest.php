<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\Category;
use App\Services\Catalog\AdSpecChips;

/**
 * @param array<int, mixed>|null $definitions
 * @param array<string, mixed>|null $values
 */
function adWithFields(?array $definitions, ?array $values, ?string $condition = null): Ad
{
    $category = new Category;
    $category->custom_fields = $definitions;

    $ad = new Ad;
    $ad->forceFill(['condition' => $condition, 'custom_fields' => $values]);
    $ad->setRelation('category', $category);

    return $ad;
}

it('lists the condition, then the flagged fields in the category order', function (): void {
    $ad = adWithFields([
        ['key' => 'storage_gb', 'type' => 'select', 'options' => ['256'], 'show_in_card' => true, 'label' => ['ar' => 'السعة', 'en' => 'Storage']],
        ['key' => 'color', 'type' => 'text', 'label' => ['en' => 'Color']],
        ['key' => 'dual_sim', 'type' => 'boolean', 'show_in_card' => 'true', 'label' => ['en' => 'Dual SIM']],
    ], ['storage_gb' => '256', 'color' => 'Black', 'dual_sim' => false], 'new');

    expect(app(AdSpecChips::class)->for($ad, 'en'))->toBe([
        ['key' => 'condition', 'label' => 'Condition', 'value' => 'New'],
        ['key' => 'storage_gb', 'label' => 'Storage', 'value' => '256'],
        ['key' => 'dual_sim', 'label' => 'Dual SIM', 'value' => 'No'],
    ]);
});

it('survives malformed admin definitions and stored values', function (): void {
    $ad = adWithFields([
        1,
        'text',
        ['key' => ['x'], 'show_in_card' => true],
        ['key' => 'make', 'type' => 'select', 'options' => ['x'], 'option_labels' => 'bad', 'show_in_card' => true],
        ['key' => 'specs', 'show_in_card' => true],
        ['key' => 'notes', 'show_in_card' => true],
    ], ['make' => 'x', 'specs' => ['ram' => 8], 'notes' => '']);

    expect(app(AdSpecChips::class)->for($ad, 'ar'))->toBe([
        ['key' => 'make', 'label' => 'make', 'value' => 'X'],
    ]);
});

it('gives no chips for a category without field definitions', function (): void {
    expect(app(AdSpecChips::class)->for(adWithFields(null, ['year' => 2020]), 'en'))->toBe([]);
});
