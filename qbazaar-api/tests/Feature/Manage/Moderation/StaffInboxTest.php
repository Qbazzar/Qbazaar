<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

use Tests\Concerns\CountsPageQueries;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class, CountsPageQueries::class);

/*
 * AD-17.10: the staff bell and inbox. Each staff member reads their own
 * notifications (new reports, pending ads, tickets, finance reviews) and the
 * work queues their permissions cover.
 */

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);
    app()->setLocale('ar');

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();
});

function inboxStaff(string $role): User
{
    return User::factory()->create()->assignRole($role);
}

/**
 * @param array<string, mixed> $data
 */
function staffNotification(User $staff, array $data = [], bool $read = false): DatabaseNotification
{
    return DatabaseNotification::query()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'notifiable_type' => $staff->getMorphClass(),
        'notifiable_id' => $staff->id,
        'data' => ['title' => 'New report', 'body' => 'Someone reported an ad.', 'category' => 'report.filed', ...$data],
        'read_at' => $read ? now() : null,
    ]);
}

it('shows every staff member their own notifications only', function (string $role): void {
    $staff = inboxStaff($role);
    staffNotification($staff, ['title' => 'Mine to read']);
    staffNotification(inboxStaff('moderator'), ['title' => 'Someone else']);
    actingAs($staff);

    $this->get(route('admin.inbox.index'))
        ->assertOk()
        ->assertSee('Mine to read')
        ->assertDontSee('Someone else')
        ->assertSee('data-staff-bell', false);
})->with(['super_admin', 'moderator', 'support']);

it('shows only the work queues the staff member can clear', function (string $role, array $visible, array $hidden): void {
    $owner = User::factory()->create();
    Ad::factory()->create([
        'user_id' => $owner->id,
        'category_id' => Category::query()->value('id'),
        'location_id' => Location::query()->value('id'),
        'status' => AdStatus::PENDING->value,
        'submitted_at' => now()->subHours(30),
    ]);
    Report::factory()->create();
    actingAs(inboxStaff($role));

    $html = $this->get(route('admin.inbox.index'))->assertOk()->getContent();

    foreach ($visible as $queue) {
        expect($html)->toContain('data-queue="' . $queue . '"');
    }
    foreach ($hidden as $queue) {
        expect($html)->not->toContain('data-queue="' . $queue . '"');
    }
})->with([
    'super_admin' => ['super_admin', ['pending_ads', 'open_reports', 'open_tickets', 'disputes'], []],
    'moderator' => ['moderator', ['pending_ads', 'open_reports', 'open_tickets'], ['disputes']],
    'support' => ['support', ['open_reports', 'open_tickets'], ['pending_ads', 'disputes']],
]);

it('flags an overdue queue on its card', function (): void {
    Ad::factory()->create([
        'user_id' => User::factory()->create()->id,
        'category_id' => Category::query()->value('id'),
        'location_id' => Location::query()->value('id'),
        'status' => AdStatus::PENDING->value,
        'submitted_at' => now()->subHours(48),
    ]);
    actingAs(inboxStaff('moderator'));

    $card = (string) str($this->get(route('admin.inbox.index'))->assertOk()->getContent())
        ->after('data-queue="pending_ads"')
        ->before('</a>');

    expect($card)->toContain(__('admin.moderation.sla.overdue'))
        ->toContain(__('admin.inbox.queues.overdue', ['count' => 1]));
});

it('filters to unread notifications', function (): void {
    $staff = inboxStaff('support');
    staffNotification($staff, ['title' => 'Still new']);
    staffNotification($staff, ['title' => 'Already seen'], read: true);
    actingAs($staff);

    $this->get(route('admin.inbox.index', ['filter' => 'unread']))
        ->assertOk()
        ->assertSee('Still new')
        ->assertDontSee('Already seen');
});

it('opens a notification: marks it read and follows its panel link', function (): void {
    $staff = inboxStaff('moderator');
    $notification = staffNotification($staff, ['cta_url' => rtrim((string) config('app.url'), '/') . '/admin/reports/abc?tab=1']);
    actingAs($staff);

    $this->post(route('admin.inbox.open', $notification->id))->assertRedirect('/admin/reports/abc?tab=1');

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('never follows a link out of the panel', function (string $url): void {
    $staff = inboxStaff('moderator');
    $notification = staffNotification($staff, ['cta_url' => $url]);
    actingAs($staff);

    $this->post(route('admin.inbox.open', $notification->id))->assertRedirect(route('admin.inbox.index'));

    expect($notification->fresh()->read_at)->not->toBeNull();
})->with([
    'another host' => ['https://evil.example/admin/reports/1'],
    'protocol-relative' => ['//evil.example/admin'],
    'a public page' => ['/ads/123'],
    'a script url' => ['javascript:alert(1)'],
    'path traversal' => ['/admin/../ads/1'],
]);

it('treats another staff member\'s notification as missing', function (): void {
    $notification = staffNotification(inboxStaff('super_admin'));
    actingAs(inboxStaff('moderator'));

    $this->post(route('admin.inbox.open', $notification->id))->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

it('marks everything read and the bell follows at once', function (): void {
    $staff = inboxStaff('support');
    staffNotification($staff);
    staffNotification($staff);
    actingAs($staff);

    $this->getJson(route('admin.inbox.summary'))->assertOk()->assertExactJson(['unread' => 2]);

    $this->post(route('admin.inbox.read-all'))->assertRedirect()->assertSessionHas('status');

    expect($staff->unreadNotifications()->count())->toBe(0);
    $this->getJson(route('admin.inbox.summary'))->assertOk()->assertExactJson(['unread' => 0]);
});

it('caches the unread count the bell polls', function (): void {
    $staff = inboxStaff('support');
    staffNotification($staff);
    actingAs($staff);

    $this->getJson(route('admin.inbox.summary'))->assertExactJson(['unread' => 1]);
    staffNotification($staff);

    // Within the cache window the bell keeps the cached figure.
    $this->getJson(route('admin.inbox.summary'))->assertExactJson(['unread' => 1]);
});

it('keeps a fixed query budget however many notifications there are', function (): void {
    $staff = inboxStaff('super_admin');
    actingAs($staff);
    $uri = route('admin.inbox.index');

    foreach (range(1, 2) as $i) {
        staffNotification($staff, ['title' => "Note {$i}"]);
    }
    $few = $this->steadyQueryCount($uri);

    foreach (range(3, 25) as $i) {
        staffNotification($staff, ['title' => "Note {$i}"]);
    }
    $many = $this->steadyQueryCount($uri);

    expect($many)->toBe($few)->and($few)->toBeLessThanOrEqual(12);
});
