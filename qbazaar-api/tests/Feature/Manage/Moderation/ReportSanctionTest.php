<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\ReportStatus;
use App\Enums\ReportTarget;
use App\Enums\SanctionAction;
use App\Enums\SanctionReason;
use App\Enums\UserStatus;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ModerationSanction;
use App\Models\Report;
use App\Models\User;
use App\Notifications\Account\UserSuspendedNotification;
use App\Notifications\Ads\AdSuspendedNotification;
use App\Services\Moderation\ReportSanctions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

/*
 * AD-17.5: act on a report in one step. The chosen sanction runs on the
 * reported item and the report closes as actioned in the same request.
 */

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);
    app()->setLocale('ar');
    Notification::fake();

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();

    $this->seller = User::factory()->create(['full_name' => 'Seller One']);
    $this->ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value, 'published_at' => now()]);
    $this->report = Report::factory()->create([
        'target_type' => ReportTarget::AD->value,
        'target_id' => $this->ad->id,
    ]);
});

function sanctionStaff(string $role): User
{
    return User::factory()->create()->assignRole($role);
}

function sanctionReport(Report $report, array $payload)
{
    return test()->post(route('admin.reports.sanction', $report), $payload);
}

it('suspends the reported ad and closes the report in one step', function (string $role): void {
    $staff = sanctionStaff($role);
    actingAs($staff);

    sanctionReport($this->report, ['action' => 'ad_suspended', 'reason' => 'fraud', 'reason_note' => 'Asked for a deposit first.'])
        ->assertRedirect(route('admin.reports.show', $this->report))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    $report = $this->report->fresh();
    $sanction = ModerationSanction::query()->sole();

    expect($this->ad->fresh()->status)->toBe(AdStatus::BLOCKED)
        ->and($report->status)->toBe(ReportStatus::ACTIONED)
        ->and($report->reviewed_by)->toBe($staff->id)
        ->and($report->admin_notes)->toContain(SanctionReason::FRAUD->adminLabel())
        ->and($sanction->action)->toBe(SanctionAction::AD_SUSPENDED)
        ->and($sanction->reason)->toBe(SanctionReason::FRAUD)
        ->and($sanction->note)->toBe('Asked for a deposit first.')
        ->and($sanction->subject_id)->toBe($this->ad->id)
        ->and($sanction->report_id)->toBe($this->report->id)
        ->and($sanction->staff_id)->toBe($staff->id);

    $adEntry = Activity::query()->where('event', 'admin.ads.suspend')->sole();
    expect($adEntry->subject_id)->toBe($this->ad->id)
        ->and($adEntry->causer_id)->toBe($staff->id)
        ->and($adEntry->properties['reason'])->toBe('fraud')
        ->and(Activity::query()->where('event', ReportSanctions::AUDIT_EVENT)->sole()->subject_id)->toBe($this->report->id);

    Notification::assertSentTo($this->seller, AdSuspendedNotification::class, fn (AdSuspendedNotification $notification): bool => $notification->reason === SanctionReason::FRAUD
        && $notification->action === SanctionAction::AD_SUSPENDED
        && $notification->adId === $this->ad->id);
})->with(['super_admin', 'moderator']);

it('refuses staff who cannot act on reports', function (): void {
    actingAs(sanctionStaff('support'));

    sanctionReport($this->report, ['action' => 'ad_suspended', 'reason' => 'fraud'])->assertForbidden();

    expect($this->ad->fresh()->status)->toBe(AdStatus::ACTIVE)
        ->and($this->report->fresh()->status)->toBe(ReportStatus::PENDING)
        ->and(ModerationSanction::query()->count())->toBe(0);
});

it('removes the reported ad only for staff who may delete ads', function (): void {
    actingAs(sanctionStaff('moderator'));
    sanctionReport($this->report, ['action' => 'ad_removed', 'reason' => 'prohibited_item'])->assertForbidden();
    expect(Ad::query()->whereKey($this->ad->id)->exists())->toBeTrue();

    actingAs(sanctionStaff('super_admin'));
    sanctionReport($this->report, ['action' => 'ad_removed', 'reason' => 'prohibited_item'])->assertSessionHasNoErrors();

    expect(Ad::withTrashed()->find($this->ad->id)?->trashed())->toBeTrue()
        ->and($this->report->fresh()->status)->toBe(ReportStatus::ACTIONED)
        ->and(ModerationSanction::query()->sole()->action)->toBe(SanctionAction::AD_REMOVED);

    Notification::assertSentTo($this->seller, AdSuspendedNotification::class, fn (AdSuspendedNotification $notification): bool => $notification->action === SanctionAction::AD_REMOVED);
});

it('suspends the account behind the report', function (Closure $makeReport, string $who): void {
    $sender = User::factory()->create();
    $sender->createToken('mobile');
    $report = $makeReport($this->ad, $this->seller, $sender);
    $target = $who === 'seller' ? $this->seller : $sender;
    actingAs(sanctionStaff('moderator'));

    sanctionReport($report, ['action' => 'user_suspended', 'reason' => 'harassment'])->assertSessionHasNoErrors();

    expect($target->fresh()->status)->toBe(UserStatus::SUSPENDED)
        ->and($report->fresh()->status)->toBe(ReportStatus::ACTIONED)
        ->and(ModerationSanction::query()->sole()->subject_id)->toBe($target->id);

    Notification::assertSentTo($target, UserSuspendedNotification::class, fn (UserSuspendedNotification $notification): bool => $notification->reason === SanctionReason::HARASSMENT);
})->with([
    'the owner of a reported ad' => [fn (Ad $ad): Report => Report::factory()->create(['target_type' => ReportTarget::AD->value, 'target_id' => $ad->id]), 'seller'],
    'a reported account' => [fn (Ad $ad, User $seller): Report => Report::factory()->create(['target_type' => ReportTarget::USER->value, 'target_id' => $seller->id]), 'seller'],
    'the sender of a reported message' => [fn (Ad $ad, User $seller, User $sender): Report => Report::factory()->create([
        'target_type' => ReportTarget::MESSAGE->value,
        'target_id' => Message::factory()->create(['sender_id' => $sender->id])->id,
    ]), 'sender'],
]);

it('offers only the moves that fit the report and the staff member', function (string $role, array $offered, array $hidden): void {
    actingAs(sanctionStaff($role));

    $html = $this->get(route('admin.reports.show', $this->report))->assertOk()->getContent();

    foreach ($offered as $action) {
        expect($html)->toContain('name="action" value="' . $action . '"');
    }
    foreach ($hidden as $action) {
        expect($html)->not->toContain('name="action" value="' . $action . '"');
    }
})->with([
    'super_admin' => ['super_admin', ['ad_suspended', 'ad_removed', 'user_suspended'], []],
    'moderator' => ['moderator', ['ad_suspended', 'user_suspended'], ['ad_removed']],
    'support' => ['support', [], ['ad_suspended', 'ad_removed', 'user_suspended']],
]);

it('offers nothing on a reported conversation or a closed report', function (Closure $makeReport): void {
    actingAs(sanctionStaff('super_admin'));
    $report = $makeReport($this->ad, $this->seller);

    $this->get(route('admin.reports.show', $report))
        ->assertOk()
        ->assertDontSee(route('admin.reports.sanction', $report), false);
})->with([
    'conversation' => [fn (Ad $ad, User $seller): Report => Report::factory()->create([
        'target_type' => ReportTarget::CONVERSATION->value,
        'target_id' => Conversation::factory()->create(['ad_id' => $ad->id, 'seller_id' => $seller->id])->id,
    ])],
    'closed' => [fn (Ad $ad): Report => Report::factory()->dismissed()->create(['target_type' => ReportTarget::AD->value, 'target_id' => $ad->id])],
]);

it('refuses a move that no longer fits, leaving the report open', function (): void {
    $this->ad->forceFill(['status' => AdStatus::BLOCKED->value])->save();
    actingAs(sanctionStaff('moderator'));

    $this->from(route('admin.reports.show', $this->report));
    sanctionReport($this->report, ['action' => 'ad_suspended', 'reason' => 'spam'])
        ->assertRedirect(route('admin.reports.show', $this->report))
        ->assertSessionHas('error', __('admin.moderation.errors.action_unavailable'));

    expect($this->report->fresh()->status)->toBe(ReportStatus::PENDING)
        ->and(ModerationSanction::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('refuses a report another moderator already closed', function (): void {
    $this->report->forceFill(['status' => ReportStatus::DISMISSED->value, 'reviewed_at' => now()])->save();
    actingAs(sanctionStaff('moderator'));

    sanctionReport($this->report, ['action' => 'ad_suspended', 'reason' => 'spam'])->assertSessionHas('error');

    expect($this->ad->fresh()->status)->toBe(AdStatus::ACTIVE)
        ->and(ModerationSanction::query()->count())->toBe(0);
});

it('validates the action and its reason', function (array $payload, string $field): void {
    actingAs(sanctionStaff('super_admin'));

    sanctionReport($this->report, $payload)->assertSessionHasErrors($field);

    expect($this->ad->fresh()->status)->toBe(AdStatus::ACTIVE)
        ->and($this->report->fresh()->status)->toBe(ReportStatus::PENDING)
        ->and(ModerationSanction::query()->count())->toBe(0);
})->with([
    'no action' => [['reason' => 'spam'], 'action'],
    'unknown action' => [['action' => 'delete_everything', 'reason' => 'spam'], 'action'],
    'no reason' => [['action' => 'ad_suspended'], 'reason'],
    'unknown reason' => [['action' => 'ad_suspended', 'reason' => 'because'], 'reason'],
    '"other" without a note' => [['action' => 'ad_suspended', 'reason' => 'other', 'reason_note' => '  '], 'reason_note'],
    'a note that is too long' => [['action' => 'ad_suspended', 'reason' => 'spam', 'reason_note' => str_repeat('a', 1001)], 'reason_note'],
    'a note with markup' => [['action' => 'ad_suspended', 'reason' => 'spam', 'reason_note' => '<script>alert(1)</script>'], 'reason_note'],
]);
