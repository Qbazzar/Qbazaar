<?php

declare(strict_types=1);

use App\Enums\SlaState;
use App\Services\Admin\Moderation\SlaClock;
use Carbon\CarbonImmutable;

$now = CarbonImmutable::parse('2026-10-18 12:00:00');

it('measures a wait against the target and its warning share', function (int $minutesAgo, SlaState $expected) use ($now): void {
    $clock = new SlaClock(targetHours: 24, warnRatio: 0.75, now: $now);

    expect($clock->stateFor($now->subMinutes($minutesAgo)))->toBe($expected);
})->with([
    'just in' => [0, SlaState::ON_TIME],
    'just under the warning share' => [18 * 60 - 1, SlaState::ON_TIME],
    'at the warning share' => [18 * 60, SlaState::DUE_SOON],
    'just under the target' => [24 * 60 - 1, SlaState::DUE_SOON],
    'at the target' => [24 * 60, SlaState::OVERDUE],
    'long past it' => [72 * 60, SlaState::OVERDUE],
]);

it('treats a time in the future as no wait at all', function () use ($now): void {
    $clock = new SlaClock(24, 0.75, $now);

    expect($clock->waitedMinutes($now->addHour()))->toBe(0)
        ->and($clock->stateFor($now->addHour()))->toBe(SlaState::ON_TIME);
});

it('names the moment after which items are overdue', function () use ($now): void {
    expect((new SlaClock(24, 0.75, $now))->overdueBefore()->toDateTimeString())->toBe('2026-10-17 12:00:00');
});

it('reads its target from config and keeps it sane', function (): void {
    config(['qbazaar.admin.moderation_sla_hours' => 0, 'qbazaar.admin.moderation_sla_warn_ratio' => 3]);

    $clock = SlaClock::fromConfig(CarbonImmutable::parse('2026-10-18 12:00:00'));

    expect($clock->targetHours)->toBe(1)->and($clock->warnRatio)->toBe(1.0);
});

it('gives each state a tone the badges know', function (): void {
    expect(SlaState::ON_TIME->tone())->toBe('success')
        ->and(SlaState::DUE_SOON->tone())->toBe('warning')
        ->and(SlaState::OVERDUE->tone())->toBe('danger');
});
