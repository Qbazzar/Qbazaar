<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\Language;
use App\Enums\SanctionAction;
use App\Enums\SanctionReason;
use App\Enums\UserStatus;
use App\Events\Ads\AdSuspended;
use App\Events\Users\UserSuspended;
use App\Models\Ad;
use App\Models\ModerationSanction;
use App\Models\User;
use App\Notifications\Account\UserSuspendedNotification;
use App\Notifications\Ads\AdSuspendedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

/*
 * AD-17.13: every suspension, removal and account suspension needs a preset
 * reason (plus a note, required with "other"). The reason is stored and
 * audited, and the affected user is told it, queued and in their language.
 */

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);
    app()->setLocale('ar');
    Notification::fake();

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();

    $this->admin = User::factory()->create()->assignRole('super_admin');
    $this->moderator = User::factory()->create()->assignRole('moderator');
    $this->seller = User::factory()->create(['language' => Language::ENGLISH->value]);
    $this->ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value, 'published_at' => now(), 'title' => 'Garden chairs']);
});

it('needs a reason to suspend an ad, and keeps it with the ad', function (): void {
    actingAs($this->moderator);

    $this->post(route('admin.ads.suspend', $this->ad))->assertSessionHasErrors('reason');
    expect($this->ad->fresh()->status)->toBe(AdStatus::ACTIVE);

    $this->post(route('admin.ads.suspend', $this->ad), ['reason' => 'misleading', 'reason_note' => 'Photos are from a catalogue.'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $sanction = ModerationSanction::query()->sole();
    expect($this->ad->fresh()->status)->toBe(AdStatus::BLOCKED)
        ->and($sanction->action)->toBe(SanctionAction::AD_SUSPENDED)
        ->and($sanction->reason)->toBe(SanctionReason::MISLEADING)
        ->and($sanction->report_id)->toBeNull()
        ->and(Activity::query()->where('event', 'admin.ads.suspend')->sole()->properties->only(['reason', 'note'])->all())
        ->toBe(['reason' => 'misleading', 'note' => 'Photos are from a catalogue.']);

    // Staff see why on the ad page, the internal note included.
    $this->get(route('admin.ads.show', $this->ad))
        ->assertOk()
        ->assertSee('id="ad-sanction-title"', false)
        ->assertSee(SanctionReason::MISLEADING->adminLabel())
        ->assertSee('Photos are from a catalogue.');

    Notification::assertSentTo($this->seller, AdSuspendedNotification::class);
});

it('needs a reason to remove an ad, alone or in bulk', function (): void {
    $other = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value]);
    actingAs($this->admin);

    $this->delete(route('admin.ads.destroy', $this->ad))->assertSessionHasErrors('reason');
    $this->post(route('admin.ads.bulk-destroy'), ['ids' => [$this->ad->id, $other->id]])->assertSessionHasErrors('reason');
    expect(Ad::query()->count())->toBe(2);

    $this->delete(route('admin.ads.destroy', $this->ad), ['reason' => 'duplicate'])->assertRedirect(route('admin.ads.index'));
    $third = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value]);
    $this->post(route('admin.ads.bulk-destroy'), ['ids' => [$other->id, $third->id], 'reason' => 'spam'])->assertSessionHasNoErrors();

    expect(Ad::query()->count())->toBe(0)
        ->and(ModerationSanction::query()->where('action', SanctionAction::AD_REMOVED->value)->count())->toBe(3)
        ->and(ModerationSanction::query()->where('reason', 'spam')->pluck('subject_id')->sort()->values()->all())
        ->toBe(collect([$other->id, $third->id])->sort()->values()->all())
        ->and(Activity::query()->where('log_name', 'admin')->where('event', 'admin.ads.destroy')->count())->toBe(3);

    Notification::assertSentToTimes($this->seller, AdSuspendedNotification::class, 3);
});

it('needs a reason to suspend an account, and shows it on the account page', function (): void {
    $customer = User::factory()->create();
    actingAs($this->moderator);

    $this->post(route('admin.users.suspend', $customer))->assertSessionHasErrors('reason');
    expect($customer->fresh()->status)->toBe(UserStatus::ACTIVE);

    $this->post(route('admin.users.suspend', $customer), ['reason' => 'other', 'reason_note' => 'Repeated no-shows reported by buyers.'])
        ->assertSessionHasNoErrors();

    expect($customer->fresh()->status)->toBe(UserStatus::SUSPENDED)
        ->and(ModerationSanction::query()->sole()->reason)->toBe(SanctionReason::OTHER);

    $this->get(route('admin.users.show', $customer))
        ->assertOk()
        ->assertSee('id="side-sanction-title"', false)
        ->assertSee('Repeated no-shows reported by buyers.');

    Notification::assertSentTo($customer, UserSuspendedNotification::class, fn (UserSuspendedNotification $notification): bool => $notification->reason === SanctionReason::OTHER);
});

it('fires AdSuspended and UserSuspended with the sanction that caused them', function (): void {
    Event::fake([AdSuspended::class, UserSuspended::class]);
    actingAs($this->admin);

    $this->post(route('admin.ads.suspend', $this->ad), ['reason' => 'spam']);
    $this->post(route('admin.users.suspend', $this->seller), ['reason' => 'fraud']);

    Event::assertDispatched(AdSuspended::class, fn (AdSuspended $event): bool => $event->ad->is($this->ad) && $event->sanction->reason === SanctionReason::SPAM);
    Event::assertDispatched(UserSuspended::class, fn (UserSuspended $event): bool => $event->user->is($this->seller) && $event->sanction->reason === SanctionReason::FRAUD);
});

it('tells the seller in their own language, with the preset reason and never the staff note', function (Language $language, string $title, string $reason): void {
    $this->seller->forceFill(['language' => $language->value])->save();
    $notification = new AdSuspendedNotification($this->ad->id, 'Garden chairs', SanctionAction::AD_SUSPENDED, SanctionReason::FRAUD);

    $payload = $notification->toArray($this->seller);
    $mail = $notification->toMail($this->seller);

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($payload['title'])->toBe($title)
        ->and($payload['body'])->toContain($reason)->toContain('Garden chairs')
        ->and($payload['category'])->toBe('ad.suspended')
        ->and($payload['reason'])->toBe('fraud')
        ->and($mail->subject)->toBe($title)
        ->and(implode(' ', $mail->introLines))->toContain($reason);
})->with([
    'English' => [Language::ENGLISH, 'Your ad was suspended', 'Suspected fraud or a scam attempt.'],
    'Arabic' => [Language::ARABIC, 'تم إيقاف إعلانك', 'اشتباه في احتيال أو محاولة نصب.'],
]);

it('words a removal and an account suspension apart', function (): void {
    $removed = (new AdSuspendedNotification($this->ad->id, 'Garden chairs', SanctionAction::AD_REMOVED, SanctionReason::DUPLICATE))->toArray($this->seller);
    $suspended = new UserSuspendedNotification(SanctionReason::HARASSMENT);

    expect($removed['category'])->toBe('ad.removed')
        ->and($removed['title'])->toBe('Your ad was removed')
        ->and($suspended)->toBeInstanceOf(ShouldQueue::class)
        ->and($suspended->toArray($this->seller)['title'])->toBe('Your account was suspended')
        ->and($suspended->toMail($this->seller)->subject)->toBe('Your account was suspended');
});

it('never puts the internal note in what the user receives', function (): void {
    actingAs($this->admin);

    $this->post(route('admin.ads.suspend', $this->ad), ['reason' => 'spam', 'reason_note' => 'internal-only-detail']);

    Notification::assertSentTo($this->seller, AdSuspendedNotification::class, function (AdSuspendedNotification $notification): bool {
        $payload = json_encode($notification->toArray($this->seller), JSON_UNESCAPED_UNICODE);

        return ! str_contains((string) $payload, 'internal-only-detail');
    });
});
