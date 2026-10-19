<?php

declare(strict_types=1);

use App\Services\Catalog\Fields\FieldDefinitions;
use App\Services\Catalog\Fields\FieldInheritance;
use App\Services\Catalog\Fields\ResolvedField;

/**
 * @param array<string, mixed> $extra
 * @return array<string, mixed>
 */
function inheritedField(string $key, array $extra = []): array
{
    return ['key' => $key, 'type' => 'text', 'label' => ['ar' => $key, 'en' => $key], ...$extra];
}

/**
 * @param list<array{id: string, fields: array<mixed>|null}> $path
 * @return list<string>
 */
function resolvedKeys(array $path): array
{
    return array_map(static fn (ResolvedField $field): string => $field->key(), (new FieldInheritance)->resolve($path)->fields);
}

it('gives a category every field of its ancestors, root first', function (): void {
    $path = [
        ['id' => 'root', 'fields' => [inheritedField('condition')]],
        ['id' => 'mid', 'fields' => null],
        ['id' => 'leaf', 'fields' => [inheritedField('year')]],
    ];

    $resolved = (new FieldInheritance)->resolve($path);

    expect(resolvedKeys($path))->toBe(['condition', 'year'])
        ->and($resolved->find('condition')?->sourceId)->toBe('root')
        ->and($resolved->find('condition')?->isDeclaredBy('leaf'))->toBeFalse()
        ->and($resolved->countDeclaredBy('leaf'))->toBe(1)
        ->and($resolved->count())->toBe(2);
});

it('lets a lower category override an inherited field with its own definition', function (): void {
    $resolved = (new FieldInheritance)->resolve([
        ['id' => 'root', 'fields' => [inheritedField('brand', ['options' => ['A', 'B'], 'type' => 'select'])]],
        ['id' => 'leaf', 'fields' => [inheritedField('brand', ['options' => ['Apple'], 'type' => 'select', 'label' => ['ar' => 'الماركة', 'en' => 'Brand']])]],
    ]);

    $brand = $resolved->find('brand');

    expect($resolved->count())->toBe(1)
        ->and($brand?->sourceId)->toBe('leaf')
        ->and($brand?->overridesAncestor)->toBeTrue()
        ->and($brand?->definition['options'])->toBe(['Apple'])
        ->and($brand?->definition['label'])->toBe(['ar' => 'الماركة', 'en' => 'Brand']);
});

it('hides an inherited field for the subtree, until a category below declares it again', function (): void {
    $hiddenAtMid = [
        ['id' => 'root', 'fields' => [inheritedField('condition'), inheritedField('colour')]],
        ['id' => 'mid', 'fields' => [['key' => 'condition', 'hidden' => true]]],
    ];

    $resolved = (new FieldInheritance)->resolve([...$hiddenAtMid, ['id' => 'leaf', 'fields' => null]]);

    expect(resolvedKeys([...$hiddenAtMid, ['id' => 'leaf', 'fields' => null]]))->toBe(['colour'])
        ->and($resolved->hiddenBy)->toBe(['condition' => 'mid'])
        ->and(resolvedKeys([...$hiddenAtMid, ['id' => 'leaf', 'fields' => [inheritedField('condition', ['sort_order' => 99])]]]))->toBe(['colour', 'condition']);
});

it('ignores a marker that hides nothing', function (): void {
    $resolved = (new FieldInheritance)->resolve([
        ['id' => 'leaf', 'fields' => [['key' => 'ghost', 'hidden' => true], inheritedField('year')]],
    ]);

    expect($resolved->hiddenBy)->toBe([])
        ->and(array_column($resolved->definitions(), 'key'))->toBe(['year']);
});

it('orders fields by sort order, ancestors first on a tie', function (): void {
    expect(resolvedKeys([
        ['id' => 'root', 'fields' => [inheritedField('a', ['sort_order' => 20]), inheritedField('b', ['sort_order' => 5])]],
        ['id' => 'leaf', 'fields' => [inheritedField('c', ['sort_order' => 20]), inheritedField('d', ['sort_order' => 1])]],
    ]))->toBe(['d', 'b', 'a', 'c']);
});

it('reads hand-typed definitions leniently into one canonical shape', function (): void {
    $definitions = FieldDefinitions::normalizeList([
        ['key' => 'storage_GB', 'type' => 'select', 'options' => ['64', 64, '', '128'], 'required' => 'false', 'filterable' => '1'],
        ['key' => 'mileage', 'type' => 'odometer', 'min' => '0', 'max' => 'lots'],
        ['key' => 'year', 'type' => 'range', 'min' => '1990', 'max' => 2026.5],
        ['type' => 'text'],
        ['key' => 'bad key!'],
        'not a field',
        ['key' => 'condition', 'hidden' => 'true', 'type' => 'select'],
    ]);

    expect($definitions)->toHaveCount(4)
        ->and($definitions[0])->toMatchArray([
            'key' => 'storage_GB',
            'type' => 'select',
            'options' => ['64', '128'],
            'required' => false,
            'filterable' => true,
            'show_in_card' => false,
            'sort_order' => 10,
            'label' => ['ar' => '', 'en' => ''],
        ])
        ->and($definitions[1])->toMatchArray(['key' => 'mileage', 'type' => 'text', 'min' => null, 'max' => null, 'options' => null])
        ->and($definitions[2])->toMatchArray(['type' => 'range', 'min' => 1990, 'max' => 2026.5, 'sort_order' => 30])
        ->and($definitions[3])->toBe(['key' => 'condition', 'hidden' => true]);
});
