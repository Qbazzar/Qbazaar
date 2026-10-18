<?php

declare(strict_types=1);

use App\Services\Catalog\Taxonomy\BulkDeletion;
use App\Services\Catalog\Taxonomy\TaxonomyKind;
use App\Services\Catalog\Taxonomy\TaxonomyUsage;

it('is in use while anything references the row', function (TaxonomyUsage $usage, bool $inUse): void {
    expect($usage->inUse())->toBe($inUse);
})->with([
    'nothing' => [new TaxonomyUsage, false],
    'an ad' => [new TaxonomyUsage(ads: 1), true],
    'a child' => [new TaxonomyUsage(children: 1), true],
    'a saved search' => [new TaxonomyUsage(savedSearches: 1), true],
    'an address' => [new TaxonomyUsage(addresses: 1), true],
]);

it('names every kind of reference with its count, in Arabic and English', function (): void {
    $usage = new TaxonomyUsage(ads: 12, children: 2, savedSearches: 1);

    app()->setLocale('ar');
    expect($usage->reasons(TaxonomyKind::CATEGORY))->toBe(['12 إعلاناً', 'تصنيفان فرعيان', 'بحث محفوظ واحد'])
        ->and($usage->blockedMessage(TaxonomyKind::CATEGORY, 'سيارات'))
        ->toBe('لا يمكن حذف التصنيف «سيارات» لأنه مستخدم: 12 إعلاناً، تصنيفان فرعيان، بحث محفوظ واحد. عطّله بدلاً من حذفه.');

    app()->setLocale('en');
    expect($usage->summary(TaxonomyKind::LOCATION))->toBe('12 ads, 2 sub-locations, 1 saved search')
        ->and((new TaxonomyUsage(addresses: 1234))->summary(TaxonomyKind::LOCATION))->toBe('1,234 user addresses');
});

it('reports a bulk delete with the rows it kept, naming the first five', function (): void {
    app()->setLocale('en');
    $blocked = array_map(
        static fn (int $i): array => ['id' => "id-{$i}", 'name' => "Row {$i}", 'usage' => new TaxonomyUsage(ads: $i)],
        range(1, 7),
    );

    $partial = new BulkDeletion(TaxonomyKind::CATEGORY, ['a', 'b'], $blocked);
    $clean = new BulkDeletion(TaxonomyKind::CATEGORY, ['a'], []);

    expect($clean->message())->toBe('1 item deleted.')
        ->and($clean->hasBlocked())->toBeFalse()
        ->and($partial->deletedCount())->toBe(2)
        ->and($partial->message())->toStartWith('2 items deleted. Kept because they are still in use: "Row 1" (1 ad); "Row 2" (2 ads)')
        ->and($partial->message())->toContain('"Row 5" (5 ads).')
        ->and($partial->message())->not->toContain('Row 6')
        ->and($partial->message())->toEndWith('And 2 more.');
});
