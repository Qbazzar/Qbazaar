<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\ReportCategory;
use App\Enums\ReportTarget;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->reporter = User::factory()->create();
    $this->seller = User::factory()->create();
    $this->ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value]);
});

it('refuses a second report on the same target within the window', function (): void {
    Sanctum::actingAs($this->reporter, ['*']);

    postJson('/api/v1/reports', [
        'target_type' => ReportTarget::AD->value,
        'target_id' => $this->ad->id,
        'category' => ReportCategory::SPAM->value,
    ])->assertStatus(201);

    postJson('/api/v1/reports', [
        'target_type' => ReportTarget::AD->value,
        'target_id' => $this->ad->id,
        'category' => ReportCategory::FRAUD->value,
    ])->assertStatus(429)
        ->assertJson(fn ($json) => $json->where('error.code', 'REPORT_002')->etc());

    expect(Report::query()->count())->toBe(1);
});

it('allows a second report once the window has elapsed', function (): void {
    Sanctum::actingAs($this->reporter, ['*']);

    // First report is filed 10 days ago — older than the default 7-day window.
    Report::factory()->create([
        'reporter_id' => $this->reporter->id,
        'target_type' => ReportTarget::AD->value,
        'target_id' => $this->ad->id,
        'category' => ReportCategory::SPAM->value,
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ]);

    postJson('/api/v1/reports', [
        'target_type' => ReportTarget::AD->value,
        'target_id' => $this->ad->id,
        'category' => ReportCategory::FRAUD->value,
    ])->assertStatus(201);

    expect(Report::query()->count())->toBe(2);
});

it('still allows reports against other targets after a recent one', function (): void {
    Sanctum::actingAs($this->reporter, ['*']);

    $otherAd = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value]);

    postJson('/api/v1/reports', [
        'target_type' => ReportTarget::AD->value,
        'target_id' => $this->ad->id,
        'category' => ReportCategory::SPAM->value,
    ])->assertStatus(201);

    postJson('/api/v1/reports', [
        'target_type' => ReportTarget::AD->value,
        'target_id' => $otherAd->id,
        'category' => ReportCategory::SPAM->value,
    ])->assertStatus(201);
});
