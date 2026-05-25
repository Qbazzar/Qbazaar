<?php

declare(strict_types=1);

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('dismisses a pending report and stamps the reviewer audit fields', function (): void {
    $admin = User::factory()->create();
    $report = Report::factory()->create();

    // Mirror the ReportResource action body — we test the model-level
    // contract, not the Filament rendering.
    $report->forceFill([
        'status' => ReportStatus::DISMISSED,
        'reviewed_at' => now(),
        'reviewed_by' => $admin->id,
    ])->save();

    $report->refresh();

    expect($report->status)->toBe(ReportStatus::DISMISSED);
    expect($report->reviewed_at)->not->toBeNull();
    expect($report->reviewed_by)->toBe($admin->id);
});

it('marks a report as actioned with admin notes', function (): void {
    $admin = User::factory()->create();
    $report = Report::factory()->create();

    $report->forceFill([
        'status' => ReportStatus::ACTIONED,
        'reviewed_at' => now(),
        'reviewed_by' => $admin->id,
        'admin_notes' => 'Suspended seller for repeated spam.',
    ])->save();

    $report->refresh();

    expect($report->status)->toBe(ReportStatus::ACTIONED);
    expect($report->admin_notes)->toBe('Suspended seller for repeated spam.');
});
