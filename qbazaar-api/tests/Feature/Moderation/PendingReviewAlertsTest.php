<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\Ads\AdPendingReviewNotification;
use App\Services\Ads\AdLifecycleService;
use App\Services\Ads\PendingReviewCounter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();
    $this->seller = User::factory()->phoneVerified()->create();
});

it('notifies every active reviewer in the panel and by email when an ad is submitted', function (): void {
    Notification::fake();

    $moderator = User::factory()->create()->assignRole('moderator');
    $superAdmin = User::factory()->create()->assignRole('super_admin');
    $customReviewer = User::factory()->create()->assignRole(Role::findOrCreate('trust_team', 'web')->givePermissionTo('ads.approve'));
    $support = User::factory()->create()->assignRole('support');
    $suspendedModerator = User::factory()->suspended()->create()->assignRole('moderator');

    Sanctum::actingAs($this->seller, ['*']);
    $draft = $this->makePublishableDraft($this->seller);

    postJson("/api/v1/ads/{$draft->id}/publish", ['accepted_terms' => true])->assertOk();

    foreach ([$moderator, $superAdmin, $customReviewer] as $reviewer) {
        Notification::assertSentTo(
            $reviewer,
            AdPendingReviewNotification::class,
            fn (AdPendingReviewNotification $notification, array $channels): bool => $notification->ad->id === $draft->id
                && $channels === ['database', 'mail'],
        );
    }

    Notification::assertNotSentTo([$support, $suspendedModerator, $this->seller], AdPendingReviewNotification::class);
});

it('queues the email on the low queue and links to the admin review page', function (): void {
    $ad = $this->makeAd($this->seller, ['status' => AdStatus::PENDING->value, 'title' => 'Reading chair']);
    $notification = new AdPendingReviewNotification($ad, flagged: true, flags: ['phone']);
    $reviewer = User::factory()->create();

    $mail = $notification->toMail($reviewer);

    expect($notification->viaQueues())->toBe(['database' => 'default', 'mail' => 'low'])
        ->and($mail->actionUrl)->toEndWith("/admin/ads/{$ad->id}")
        ->and(implode(' ', $mail->introLines))->toContain('Reading chair')->toContain('phone');
});

it('caches the pending count and refreshes it when an ad changes status', function (): void {
    $counter = app(PendingReviewCounter::class);
    $pending = $this->makeAd($this->seller, ['status' => AdStatus::PENDING->value]);

    expect($counter->count())->toBe(1)
        ->and(Cache::has(PendingReviewCounter::CACHE_KEY))->toBeTrue();

    app(AdLifecycleService::class)->approve($pending);

    expect(Cache::has(PendingReviewCounter::CACHE_KEY))->toBeFalse()
        ->and($counter->count())->toBe(0);

    $this->makeAd($this->seller, ['status' => AdStatus::PENDING->value]);

    expect($counter->count())->toBe(1);
});

it('refreshes the pending count when a pending ad is deleted', function (): void {
    $counter = app(PendingReviewCounter::class);
    $pending = $this->makeAd($this->seller, ['status' => AdStatus::PENDING->value]);
    expect($counter->count())->toBe(1);

    $pending->delete();

    expect($counter->count())->toBe(0);
});

it('shows the pending count in the panel to reviewers only', function (): void {
    $this->makeAd($this->seller, ['status' => AdStatus::PENDING->value]);
    $this->makeAd($this->seller, ['status' => AdStatus::PENDING->value]);

    actingAs(User::factory()->create()->assignRole('moderator'));
    get('/admin')->assertOk()->assertSee(__('admin.ad_review.pending_badge'), false);

    actingAs(User::factory()->create(['status' => UserStatus::ACTIVE])->assignRole('support'));
    get('/admin')->assertOk()->assertDontSee(__('admin.ad_review.pending_badge'), false);
});
