<?php

declare(strict_types=1);

use App\Services\Health\CheckStatus;
use App\Services\Health\ReadinessReport;

it('is ok when every check passes or is not used here', function (): void {
    $report = new ReadinessReport(['database' => CheckStatus::OK, 'redis' => CheckStatus::DISABLED], ['database', 'redis']);

    expect($report->isReady())->toBeTrue()
        ->and($report->status())->toBe(ReadinessReport::OK)
        ->and($report->toArray())->toBe(['status' => 'ok', 'checks' => ['database' => 'ok', 'redis' => 'disabled']]);
});

it('is degraded but still ready when only a background check fails', function (CheckStatus $status): void {
    $report = new ReadinessReport(['database' => CheckStatus::OK, 'scheduler' => $status], ['database']);

    expect($report->isReady())->toBeTrue()
        ->and($report->status())->toBe(ReadinessReport::DEGRADED);
})->with([CheckStatus::FAIL, CheckStatus::STALE, CheckStatus::UNKNOWN]);

it('is down when a critical check fails', function (): void {
    $report = new ReadinessReport(['database' => CheckStatus::FAIL, 'search' => CheckStatus::OK], ['database']);

    expect($report->isReady())->toBeFalse()
        ->and($report->status())->toBe(ReadinessReport::DOWN);
});

it('treats a critical check that never ran as down', function (): void {
    $report = new ReadinessReport(['search' => CheckStatus::OK], ['database']);

    expect($report->isReady())->toBeFalse();
});
