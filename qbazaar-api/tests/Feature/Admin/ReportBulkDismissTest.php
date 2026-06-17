<?php

declare(strict_types=1);

use App\Enums\ReportStatus;
use App\Filament\Admin\Resources\ReportResource\Pages\ListReports;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
    actingAs($this->admin);
});

it('bulk-dismisses pending reports and stamps the reviewer', function (): void {
    $pending = Report::factory()->count(3)->create(['status' => ReportStatus::PENDING->value]);

    Livewire::test(ListReports::class)
        ->callTableBulkAction('bulk_dismiss', $pending);

    $pending->each(function (Report $report): void {
        $report->refresh();
        expect($report->status)->toBe(ReportStatus::DISMISSED);
        expect($report->reviewed_by)->toBe($this->admin->id);
        expect($report->reviewed_at)->not->toBeNull();
    });
});

it('leaves already-resolved reports unchanged during bulk dismiss', function (): void {
    $actioned = Report::factory()->create([
        'status' => ReportStatus::ACTIONED->value,
    ]);

    Livewire::test(ListReports::class)
        ->removeTableFilters()
        ->callTableBulkAction('bulk_dismiss', collect([$actioned]));

    expect($actioned->refresh()->status)->toBe(ReportStatus::ACTIONED);
});
