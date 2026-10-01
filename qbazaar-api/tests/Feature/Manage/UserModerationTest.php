<?php

declare(strict_types=1);

use App\Enums\ReportStatus;
use App\Enums\ReportTarget;
use App\Enums\UserStatus;
use App\Models\DeviceToken;
use App\Models\Report;
use App\Models\User;
use App\Services\Users\UserModerationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
    $this->moderator = User::factory()->create()->assignRole('moderator');
});

it('signs a suspended user out of every session and device', function (): void {
    $customer = User::factory()->create();
    $customer->createToken('mobile');
    DeviceToken::factory()->create(['user_id' => $customer->id]);

    app(UserModerationService::class)->suspend($this->moderator, $customer);

    expect($customer->fresh()->status)->toBe(UserStatus::SUSPENDED)
        ->and($customer->tokens()->count())->toBe(0)
        ->and($customer->deviceTokens()->count())->toBe(0);
});

it('reactivates a suspended user', function (): void {
    $customer = User::factory()->suspended()->create();

    app(UserModerationService::class)->activate($this->moderator, $customer);

    expect($customer->fresh()->status)->toBe(UserStatus::ACTIVE);
});

it('enforces the staff hierarchy for suspend and activate', function (string $method): void {
    $superAdmin = User::factory()->create()->assignRole('super_admin');

    expect(fn () => app(UserModerationService::class)->{$method}($this->moderator, $superAdmin))
        ->toThrow(HttpException::class);

    expect($superAdmin->fresh()->status)->toBe(UserStatus::ACTIVE);
})->with(['suspend', 'activate']);

it('suspends the reported user from the reports queue through the same rules', function (): void {
    $customer = User::factory()->create();
    $customer->createToken('mobile');
    $report = Report::factory()->create([
        'target_type' => ReportTarget::USER->value,
        'target_id' => $customer->id,
    ]);

    actingAs($this->moderator)->post("/admin/reports/{$report->id}/ban-user")->assertRedirect();

    expect($customer->fresh()->status)->toBe(UserStatus::SUSPENDED)
        ->and($customer->tokens()->count())->toBe(0)
        ->and($report->fresh()->status)->toBe(ReportStatus::ACTIONED);
});

it('refuses to suspend higher-ranked staff from the reports queue', function (): void {
    $superAdmin = User::factory()->create()->assignRole('super_admin');
    $report = Report::factory()->create([
        'target_type' => ReportTarget::USER->value,
        'target_id' => $superAdmin->id,
    ]);

    actingAs($this->moderator)->post("/admin/reports/{$report->id}/ban-user")->assertForbidden();

    expect($superAdmin->fresh()->status)->toBe(UserStatus::ACTIVE)
        ->and($report->fresh()->status)->toBe(ReportStatus::PENDING);
});
