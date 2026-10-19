<?php

declare(strict_types=1);

use App\Services\Catalog\Fields\FieldOptionLines;

it('reads one option per line with optional labels, skipping blank lines', function (): void {
    $rows = FieldOptionLines::parse("automatic | أوتوماتيك | Automatic\r\n\n  manual|عادي \nOther");

    expect($rows)->toBe([
        ['value' => 'automatic', 'ar' => 'أوتوماتيك', 'en' => 'Automatic'],
        ['value' => 'manual', 'ar' => 'عادي', 'en' => ''],
        ['value' => 'Other', 'ar' => '', 'en' => ''],
    ]);
});

it('turns the rows into options and labels, leaving unlabelled options out of the labels', function (): void {
    expect(FieldOptionLines::toDefinition(FieldOptionLines::parse("new | جديد | New\nused")))->toBe([
        'options' => ['new', 'used'],
        'option_labels' => ['new' => ['ar' => 'جديد', 'en' => 'New']],
    ]);
});

it('formats a definition back into the text it was typed as', function (): void {
    $text = "automatic | أوتوماتيك | Automatic\nmanual | عادي | \nOther";

    expect(FieldOptionLines::format(FieldOptionLines::toDefinition(FieldOptionLines::parse($text))))->toBe($text)
        ->and(FieldOptionLines::format(['options' => null]))->toBe('');
});
