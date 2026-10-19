<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\Language;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Notifications\Ads\AdRejectedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CountsPageQueries;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class, CountsPageQueries::class);

/*
 * AD-17.10: the FIFO review queue. Pending ads oldest submission first, each
 * measured against the review target, approved or rejected in bulk.
 */

beforeEach(function (): void {
    $this->withoutVite();
    config([
        'scout.driver' => null,
        'qbazaar.admin.moderation_sla_hours' => 24,
        'qbazaar.admin.moderation_sla_warn_ratio' => 0.75,
    ]);
    app()->setLocale('ar');
    Notification::fake();

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();

    $this->seller = User::factory()->create(['language' => Language::ENGLISH->value]);
    $this->moderator = User::factory()->create()->assignRole('moderator');
});

function pendingAd(User $seller, string $title, int $hoursAgo): Ad
{
    return Ad::factory()->create([
        'user_id' => $seller->id,
        'category_id' => Category::query()->value('id'),
        'location_id' => Location::query()->value('id'),
        'title' => $title,
        'status' => AdStatus::PENDING->value,
        'submitted_at' => now()->subHours($hoursAgo),
        'published_at' => null,
    ]);
}

it('lists pending ads oldest first, each against the review target', function (string $role): void {
    pendingAd($this->seller, 'Fresh bicycle', 1);
    pendingAd($this->seller, 'Overdue sofa', 30);
    pendingAd($this->seller, 'Due-soon lamp', 20);
    $this->makeAd($this->seller, ['title' => 'Live table', 'status' => AdStatus::ACTIVE->value]);
    actingAs(User::factory()->create()->assignRole($role));

    $html = $this->get(route('admin.moderation.queue'))
        ->assertOk()
        ->assertSee(__('admin.review_queue.title'))
        ->assertDontSee('Live table')
        ->getContent();

    $sofa = strpos($html, 'Overdue sofa');
    $lamp = strpos($html, 'Due-soon lamp');
    $bicycle = strpos($html, 'Fresh bicycle');

    expect($sofa)->toBeLessThan($lamp)
        ->and($lamp)->toBeLessThan($bicycle)
        ->and(substr_count($html, 'data-sla="overdue"'))->toBe(1)
        ->and(substr_count($html, 'data-sla="due_soon"'))->toBe(1)
        ->and(substr_count($html, 'data-sla="on_time"'))->toBe(1)
        ->and($html)->toContain('bg-danger-50/60');
})->with(['super_admin', 'moderator']);

it('is closed to staff who cannot approve ads', function (): void {
    actingAs(User::factory()->create()->assignRole('support'));

    $this->get(route('admin.moderation.queue'))->assertForbidden();
    $this->post(route('admin.moderation.queue.approve'), ['ids' => [pendingAd($this->seller, 'x', 1)->id]])->assertForbidden();
});

it('approves the selected ads in bulk and skips those no longer waiting', function (): void {
    $first = pendingAd($this->seller, 'One', 3);
    $second = pendingAd($this->seller, 'Two', 2);
    $live = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value]);
    actingAs($this->moderator);

    $this->post(route('admin.moderation.queue.approve'), ['ids' => [$first->id, $second->id, $live->id]])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', __('admin.review_queue.flash.approved', ['count' => 2]) . ' ' . __('admin.review_queue.flash.skipped', ['count' => 1]));

    expect($first->fresh()->status)->toBe(AdStatus::ACTIVE)
        ->and($second->fresh()->status)->toBe(AdStatus::ACTIVE)
        ->and(Activity::query()->where('log_name', 'admin')->where('event', 'admin.ads.approve')->count())->toBe(2)
        ->and(Activity::query()->where('event', 'admin.ads.approve')->first()->properties['bulk'])->toBeTrue();
});

it('rejects the selected ads in bulk with one reason the sellers read in their language', function (): void {
    $first = pendingAd($this->seller, 'One', 3);
    $second = pendingAd($this->seller, 'Two', 2);
    actingAs($this->moderator);

    $this->post(route('admin.moderation.queue.reject'), ['ids' => [$first->id, $second->id]])->assertSessionHasErrors('reason');
    expect($first->fresh()->status)->toBe(AdStatus::PENDING);

    $this->post(route('admin.moderation.queue.reject'), ['ids' => [$first->id, $second->id], 'reason' => 'wrong_category', 'reason_note' => 'Belongs in furniture.'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', __('admin.review_queue.flash.rejected', ['count' => 2]));

    expect($first->fresh()->status)->toBe(AdStatus::REJECTED)
        ->and($second->fresh()->status)->toBe(AdStatus::REJECTED)
        ->and(Activity::query()->where('event', 'admin.ads.reject')->count())->toBe(2)
        ->and(Activity::query()->where('event', 'admin.ads.reject')->first()->properties['reason'])->toBe('wrong_category');

    Notification::assertSentToTimes($this->seller, AdRejectedNotification::class, 2);
    Notification::assertSentTo($this->seller, AdRejectedNotification::class, function (AdRejectedNotification $notification): bool {
        $mail = implode(' ', $notification->toMail($this->seller)->introLines);

        return str_contains($mail, 'The listing is in the wrong category.') && ! str_contains($mail, 'Belongs in furniture.');
    });
});

it('validates the bulk selection and the rejection reason', function (string $route, array $payload, string $field): void {
    actingAs($this->moderator);
    $ad = pendingAd($this->seller, 'One', 3);

    $this->post(route($route), array_map(fn (mixed $value): mixed => $value === '{ad}' ? $ad->id : $value, $payload))
        ->assertSessionHasErrors($field);

    expect($ad->fresh()->status)->toBe(AdStatus::PENDING);
})->with([
    'nothing selected' => ['admin.moderation.queue.approve', ['ids' => []], 'ids'],
    'an id that is not a ULID' => ['admin.moderation.queue.approve', ['ids' => ['1 OR 1=1']], 'ids.0'],
    'reject without a reason' => ['admin.moderation.queue.reject', ['ids' => ['{ad}']], 'reason'],
    'reject with "other" and no note' => ['admin.moderation.queue.reject', ['ids' => ['{ad}'], 'reason' => 'other'], 'reason_note'],
]);

it('caps how many ads one bulk review can move', function (): void {
    config(['qbazaar.admin.bulk_action_max' => 1]);
    actingAs($this->moderator);

    $this->post(route('admin.moderation.queue.approve'), ['ids' => [pendingAd($this->seller, 'a', 2)->id, pendingAd($this->seller, 'b', 1)->id]])
        ->assertSessionHasErrors('ids');

    expect(Ad::query()->where('status', AdStatus::PENDING->value)->count())->toBe(2);
});

it('shows the waiting total, the overdue count and the oldest wait', function (): void {
    pendingAd($this->seller, 'Overdue one', 40);
    pendingAd($this->seller, 'Overdue two', 25);
    pendingAd($this->seller, 'Fresh', 1);
    actingAs($this->moderator);

    $this->get(route('admin.moderation.queue'))
        ->assertOk()
        ->assertSee('text-3xl font-extrabold">3</div>', false)
        ->assertSee('<span class="tabular-nums text-danger-700">2</span>', false)
        ->assertSee(__('admin.moderation.sla.overdue'));
});

it('puts the pending badge and the queue link in the sidebar', function (): void {
    pendingAd($this->seller, 'Waiting', 1);
    actingAs($this->moderator);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(route('admin.moderation.queue'), false)
        ->assertSee(__('admin.review_queue.nav'));
});

it('keeps a fixed query budget however many ads wait', function (): void {
    actingAs($this->moderator);
    $uri = route('admin.moderation.queue');

    foreach (range(1, 2) as $i) {
        pendingAd(User::factory()->create(), "Ad {$i}", $i);
    }
    $few = $this->steadyQueryCount($uri);

    foreach (range(3, 20) as $i) {
        pendingAd(User::factory()->create(), "Ad {$i}", $i);
    }
    $many = $this->steadyQueryCount($uri);

    expect($many)->toBe($few)->and($few)->toBeLessThanOrEqual(14);
});
