<?php

declare(strict_types=1);

use App\Services\Finance\Revenue\RevenueRange;
use App\Support\Sql\LocalBucket;
use Carbon\CarbonImmutable;

/** 2026-10-13 14:30 in Qatar (UTC+3). */
function qatarNow(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-10-13 14:30:00', 'Asia/Qatar');
}

it('starts every preset at a local midnight', function (RevenueRange $range, string $start, string $end): void {
    $window = $range->window(qatarNow());

    expect($window->start->format('Y-m-d H:i'))->toBe($start)->and($window->end->format('Y-m-d H:i'))->toBe($end);
})->with([
    'today' => [RevenueRange::TODAY, '2026-10-13 00:00', '2026-10-13 14:30'],
    '7 days' => [RevenueRange::WEEK, '2026-10-07 00:00', '2026-10-13 14:30'],
    '30 days' => [RevenueRange::MONTH, '2026-09-14 00:00', '2026-10-13 14:30'],
    'this month' => [RevenueRange::THIS_MONTH, '2026-10-01 00:00', '2026-10-13 14:30'],
    'last month' => [RevenueRange::LAST_MONTH, '2026-09-01 00:00', '2026-10-01 00:00'],
    'this year' => [RevenueRange::THIS_YEAR, '2026-01-01 00:00', '2026-10-13 14:30'],
]);

it('compares with a like-for-like previous period', function (): void {
    $today = RevenueRange::TODAY->window(qatarNow());
    $last = RevenueRange::LAST_MONTH->window(qatarNow());
    $thisMonth = RevenueRange::THIS_MONTH->window(qatarNow());

    expect($today->previousStart->format('Y-m-d H:i'))->toBe('2026-10-12 00:00')
        ->and($today->previousEnd->format('Y-m-d H:i'))->toBe('2026-10-12 14:30')
        ->and($last->previousStart->format('Y-m-d'))->toBe('2026-08-01')
        ->and($last->previousEnd->format('Y-m-d'))->toBe('2026-09-01')
        ->and($thisMonth->previousStart->format('Y-m-d'))->toBe('2026-09-01')
        ->and($thisMonth->previousEnd->format('Y-m-d H:i'))->toBe('2026-09-13 14:30');
});

it('clamps a custom range to now and treats the last day as inclusive', function (): void {
    $window = RevenueRange::CUSTOM->window(qatarNow(), CarbonImmutable::parse('2026-09-01', 'Asia/Qatar'), CarbonImmutable::parse('2026-09-30', 'Asia/Qatar'));

    expect($window->start->toDateString())->toBe('2026-09-01')
        ->and($window->end->format('Y-m-d H:i'))->toBe('2026-10-01 00:00')
        ->and($window->days())->toBe(30);
});

it('buckets by day, week or month depending on the length', function (): void {
    $year = RevenueRange::THIS_YEAR->window(qatarNow());
    $month = RevenueRange::MONTH->window(qatarNow());
    $long = RevenueRange::CUSTOM->window(qatarNow(), CarbonImmutable::parse('2024-11-01', 'Asia/Qatar'), CarbonImmutable::parse('2026-10-01', 'Asia/Qatar'));

    expect($month->bucket())->toBe(LocalBucket::DAY)->and($month->bucketStarts())->toHaveCount(30)
        ->and($year->bucket())->toBe(LocalBucket::WEEK)
        ->and($long->bucket())->toBe(LocalBucket::MONTH);
    expect($year->bucketStarts()[0])->toBe('2025-12-29');
});

it('builds the same cache key for the same range on the same day', function (): void {
    expect(RevenueRange::WEEK->window(qatarNow())->cacheKey())->toBe(RevenueRange::WEEK->window(qatarNow()->addHour())->cacheKey())
        ->and(RevenueRange::WEEK->window(qatarNow())->cacheKey())->not->toBe(RevenueRange::WEEK->window(qatarNow()->addDay())->cacheKey());
});
