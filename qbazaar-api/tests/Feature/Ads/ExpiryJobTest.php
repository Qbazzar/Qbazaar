<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\Language;
use App\Enums\PlatformSetting;
use App\Events\Ads\AdExpired;
use App\Events\Ads\AdExpiringSoon;
use App\Jobs\Ads\ExpireOldAdsJob;
use App\Models\Ad;
use App\Models\User;
use App\Notifications\Ads\AdExpiringSoonNotification;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
});

function runExpiryJob(): void
{
    app()->call([new ExpireOldAdsJob, 'handle']);
}

function activeAdExpiringAt(User $seller, DateTimeInterface $expiresAt): Ad
{
    return Ad::factory()->active()->create([
        'user_id' => $seller->id,
        'expires_at' => $expiresAt,
    ]);
}

it('flips ACTIVE ads past expires_at into EXPIRED and fires AdExpired', function (): void {
    Event::fake([AdExpired::class, AdExpiringSoon::class]);

    $stale = Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'expires_at' => now()->subDay(),
    ]);

    $fresh = Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'expires_at' => now()->addDays(5),
    ]);

    runExpiryJob();

    expect($stale->fresh()->status)->toBe(AdStatus::EXPIRED)
        ->and($fresh->fresh()->status)->toBe(AdStatus::ACTIVE);

    Event::assertDispatched(AdExpired::class, function (AdExpired $e) use ($stale): bool {
        return $e->ad->id === $stale->id;
    });
});

it('warns about ads inside the default three-day window', function (): void {
    Event::fake([AdExpired::class, AdExpiringSoon::class]);

    $insideWindow = activeAdExpiringAt($this->seller, now()->addDays(2));
    activeAdExpiringAt($this->seller, now()->addDays(4));

    runExpiryJob();

    Event::assertDispatched(AdExpiringSoon::class, 1);
    Event::assertDispatched(AdExpiringSoon::class, fn (AdExpiringSoon $e): bool => $e->ad->id === $insideWindow->id);
    expect($insideWindow->fresh()->expiring_notified_at)->not->toBeNull();
});

it('uses the admin-set warning window', function (): void {
    Event::fake([AdExpired::class, AdExpiringSoon::class]);

    app(SettingsService::class)->put(PlatformSetting::AD_EXPIRY_WARNING_DAYS, 5, User::factory()->create());
    $fourDaysOut = activeAdExpiringAt($this->seller, now()->addDays(4));

    runExpiryJob();

    Event::assertDispatched(AdExpiringSoon::class, fn (AdExpiringSoon $e): bool => $e->ad->id === $fourDaysOut->id);
});

it('warns about an ad only once per expiry', function (): void {
    Event::fake([AdExpired::class, AdExpiringSoon::class]);

    activeAdExpiringAt($this->seller, now()->addDay());

    runExpiryJob();
    runExpiryJob();

    Event::assertDispatched(AdExpiringSoon::class, 1);
});

it('warns again after the ad is renewed', function (): void {
    Event::fake([AdExpired::class, AdExpiringSoon::class]);

    $ad = activeAdExpiringAt($this->seller, now()->addDay());
    runExpiryJob();

    $ad->renew();
    expect($ad->fresh()->expiring_notified_at)->toBeNull();

    $this->travelTo($ad->fresh()->expires_at->subDay());
    runExpiryJob();

    Event::assertDispatched(AdExpiringSoon::class, 2);
});

it('expires every due ad even when expiry order differs from key order', function (): void {
    Event::fake([AdExpired::class, AdExpiringSoon::class]);

    // Later rows expire earlier: the old orderBy(expires_at) + chunkById walk skipped these.
    foreach (range(1, 150) as $position) {
        activeAdExpiringAt($this->seller, now()->subMinutes($position));
    }

    runExpiryJob();

    expect(Ad::query()->where('status', AdStatus::ACTIVE->value)->count())->toBe(0);
    Event::assertDispatched(AdExpired::class, 150);
});

it('tells the seller the expiry in Qatar time and their own language', function (): void {
    $ad = activeAdExpiringAt($this->seller, Carbon::parse('2026-10-03 21:30:00', 'UTC'));
    $notification = new AdExpiringSoonNotification($ad);

    $arabicBody = $notification->toArray($this->seller)['body'];

    $this->seller->forceFill(['language' => Language::ENGLISH->value])->save();
    $englishBody = $notification->toArray($this->seller)['body'];

    expect($arabicBody)->toContain('4 أكتوبر 2026')
        ->and($englishBody)->toContain('4 October 2026, 12:30 AM');
});
