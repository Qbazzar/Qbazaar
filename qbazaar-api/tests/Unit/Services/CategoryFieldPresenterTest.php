<?php

declare(strict_types=1);

use App\Services\Catalog\CategoryFieldPresenter;

it('labels each option in the requested language and drops the internal labels map', function (): void {
    $field = (new CategoryFieldPresenter)->field([
        'key' => 'fuel_type',
        'type' => 'select',
        'options' => ['petrol', 'semi_electric', 'BMW'],
        'option_labels' => ['petrol' => ['ar' => 'بنزين', 'en' => 'Petrol']],
        'show_in_card' => true,
    ], 'ar');

    expect($field['options_labeled'])->toBe([
        ['value' => 'petrol', 'label' => 'بنزين'],
        ['value' => 'semi_electric', 'label' => 'Semi electric'],
        ['value' => 'BMW', 'label' => 'BMW'],
    ])
        ->and($field['show_in_card'])->toBeTrue()
        ->and($field)->not->toHaveKey('option_labels');
});

it('reads the card flag the way an admin means it', function (mixed $flag, bool $shows): void {
    expect((new CategoryFieldPresenter)->showsInCard(['key' => 'year', 'show_in_card' => $flag]))->toBe($shows);
})->with([
    'true' => [true, true],
    'string true' => ['true', true],
    'one' => [1, true],
    'false' => [false, false],
    'string false' => ['false', false],
    'string no' => ['no', false],
    'array' => [['yes'], false],
]);

it('survives malformed admin definitions', function (): void {
    $presenter = new CategoryFieldPresenter;
    $badLabels = ['key' => 'make', 'type' => 'select', 'options' => ['x', ['nested']], 'option_labels' => 'bad'];
    $badTranslations = ['key' => 'model', 'type' => 'select', 'options' => ['a'], 'option_labels' => ['a' => ['ar' => ['not text'], 'en' => '']]];

    expect($presenter->present([1, ['key' => ['x']], $badLabels, $badTranslations], 'ar'))->toBe([
        1,
        ['key' => ['x'], 'options_labeled' => null, 'show_in_card' => false],
        [...array_diff_key($badLabels, ['option_labels' => true]), 'options_labeled' => [
            ['value' => 'x', 'label' => 'X'],
            ['value' => ['nested'], 'label' => ''],
        ], 'show_in_card' => false],
        [...array_diff_key($badTranslations, ['option_labels' => true]), 'options_labeled' => [
            ['value' => 'a', 'label' => 'A'],
        ], 'show_in_card' => false],
    ])
        ->and($presenter->label(['key' => 'year', 'label' => 'not localised'], 'ar'))->toBe('year')
        ->and($presenter->present(null, 'ar'))->toBeNull();
});
