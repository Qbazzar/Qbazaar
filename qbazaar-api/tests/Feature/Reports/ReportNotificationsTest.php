<?php

declare(strict_types=1);

use App\Enums\ReportStatus;
use App\Events\Reports\ReportCreated;
use App\Listeners\Reports\NotifyModeratorsOfReport;
use App\Models\Report;
use App\Models\User;
use App\Notifications\Reports\ReportFiledNotification;
use App\Notifications\Reports\ReportResolvedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
});

it('queues the moderator alert after commit so filing a report never waits on it', function (): void {
    $listener = new ReflectionClass(NotifyModeratorsOfReport::class);

    expect($listener->implementsInterface(ShouldQueueAfterCommit::class))->toBeTrue()
        ->and($listener->getDefaultProperties())->toMatchArray(['queue' => 'notifications', 'tries' => 3, 'timeout' => 60]);
});

it('puts a new report in the bell of active staff who can act on reports', function (): void {
    $moderator = User::factory()->create()->assignRole('moderator');
    $superAdmin = User::factory()->create()->assignRole('super_admin');
    $suspendedModerator = User::factory()->suspended()->create()->assignRole('moderator');
    $customer = User::factory()->create();
    $report = Report::factory()->create();

    app(NotifyModeratorsOfReport::class)->handle(new ReportCreated($report));

    foreach ([$moderator, $superAdmin] as $handler) {
        Notification::assertSentTo(
            $handler,
            ReportFiledNotification::class,
            fn (ReportFiledNotification $notification, array $channels): bool => $notification->report->is($report)
                && $channels === ['database'],
        );
    }

    Notification::assertNotSentTo([$suspendedModerator, $customer, $report->reporter], ReportFiledNotification::class);
});

it('skips the alert when the report was handled before the queued listener ran', function (): void {
    User::factory()->create()->assignRole('moderator');
    $report = Report::factory()->dismissed()->create();

    app(NotifyModeratorsOfReport::class)->handle(new ReportCreated($report));

    Notification::assertNothingSent();
});

it('links the staff alert to the report page', function (): void {
    $report = Report::factory()->create();

    $payload = (new ReportFiledNotification($report))->toArray(User::factory()->create());

    expect($payload['cta_url'])->toEndWith('/admin/reports/' . $report->id)
        ->and($payload['report_id'])->toBe($report->id)
        ->and($payload['title'])->toBeString()->not->toBe('admin.report_filed.title');
});

it('tells the reporter once when staff take action', function (): void {
    $report = Report::factory()->create();
    actingAs(User::factory()->create()->assignRole('moderator'));

    $this->post("/admin/reports/{$report->id}/action", ['admin_notes' => 'Ad removed'])->assertRedirect();
    $this->post("/admin/reports/{$report->id}/dismiss")->assertRedirect();

    expect($report->fresh()->status)->toBe(ReportStatus::DISMISSED);
    Notification::assertSentToTimes($report->reporter, ReportResolvedNotification::class, 1);
    Notification::assertSentTo(
        $report->reporter,
        ReportResolvedNotification::class,
        fn (ReportResolvedNotification $notification): bool => $notification->toArray($report->reporter)['outcome'] === ReportStatus::ACTIONED->value,
    );
});

it('keeps an internal "reviewed" mark silent', function (): void {
    $report = Report::factory()->create();
    actingAs(User::factory()->create()->assignRole('moderator'));

    $this->post("/admin/reports/{$report->id}/resolve")->assertRedirect();

    expect($report->fresh()->status)->toBe(ReportStatus::REVIEWED);
    Notification::assertNothingSentTo($report->reporter);
});

it('tells every reporter in a bulk dismiss without naming any sanction', function (): void {
    $reports = Report::factory()->count(2)->create();
    actingAs(User::factory()->create()->assignRole('moderator'));

    $this->post('/admin/reports/bulk-dismiss', ['ids' => $reports->pluck('id')->all()])->assertRedirect();

    foreach ($reports as $report) {
        Notification::assertSentTo(
            $report->reporter,
            ReportResolvedNotification::class,
            fn (ReportResolvedNotification $notification): bool => ! array_key_exists('admin_notes', $notification->toArray($report->reporter)),
        );
    }
});

it('rejects a bulk dismiss above the configured batch size', function (): void {
    config(['qbazaar.admin.bulk_action_max' => 1]);
    $reports = Report::factory()->count(2)->create();
    actingAs(User::factory()->create()->assignRole('moderator'));

    $this->post('/admin/reports/bulk-dismiss', ['ids' => $reports->pluck('id')->all()])->assertSessionHasErrors('ids');

    expect(Report::query()->where('status', ReportStatus::PENDING->value)->count())->toBe(2);
});
