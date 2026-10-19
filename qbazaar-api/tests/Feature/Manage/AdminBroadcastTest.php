<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\BroadcastSegment;
use App\Enums\BroadcastStatus;
use App\Enums\Language;
use App\Enums\LocationType;
use App\Enums\UserStatus;
use App\Jobs\Broadcasts\DeliverBroadcastChunkJob;
use App\Jobs\Broadcasts\FanOutBroadcastJob;
use App\Models\Location;
use App\Models\StaffBroadcast;
use App\Models\User;
use App\Notifications\SystemAnnouncementNotification;
use App\Services\Admin\Broadcasts\BroadcastDelivery;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CountsPageQueries;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class, CountsPageQueries::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['scout.driver' => null]);
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();

    $this->sender = User::factory()->create()->assignRole('moderator');
});

/** @param array<string, mixed> $overrides */
function broadcastPayload(array $overrides = []): array
{
    return [
        'segment' => 'all',
        'title_ar' => 'تحديث مهم',
        'body_ar' => 'نص الإشعار بالعربية',
        'title_en' => 'Important update',
        'body_en' => 'The notification text in English',
        ...$overrides,
    ];
}

function broadcastCity(): Location
{
    return Location::query()->where('type', LocationType::CITY->value)->whereHas('children')->firstOrFail();
}

describe('the composer page', function (): void {
    it('opens for every staff role that may broadcast', function (string $role): void {
        actingAs(User::factory()->create()->assignRole($role));

        $this->get(route('admin.broadcasts.index'))
            ->assertOk()
            ->assertSee(__('admin.broadcasts.compose_title'))
            ->assertSee(__('admin.broadcasts.history_title'))
            ->assertSee(broadcastCity()->getLocalizedName(app()->getLocale()));
    })->with(['super_admin', 'moderator', 'support']);

    it('is refused to staff whose role lost notifications.broadcast', function (): void {
        $support = User::factory()->create()->assignRole('support');
        $support->roles->first()->revokePermissionTo('notifications.broadcast');
        actingAs($support);

        $this->get(route('admin.broadcasts.index'))->assertForbidden();
        $this->post(route('admin.broadcasts.store'), broadcastPayload())->assertForbidden();

        expect(StaffBroadcast::query()->count())->toBe(0);
    });

    it('keeps its query count flat as the history grows', function (): void {
        actingAs($this->sender);
        $uri = route('admin.broadcasts.index');
        StaffBroadcast::query()->forceCreate(['sender_id' => $this->sender->id, 'segment' => 'city', 'location_id' => broadcastCity()->id, 'title_ar' => 'أ', 'body_ar' => 'ب', 'status' => 'sent']);
        $few = $this->steadyQueryCount($uri);

        foreach (range(1, 12) as $i) {
            StaffBroadcast::query()->forceCreate([
                'sender_id' => User::factory()->create()->assignRole('support')->id,
                'segment' => 'city',
                'location_id' => broadcastCity()->id,
                'title_ar' => "عنوان {$i}",
                'body_ar' => 'نص',
                'status' => 'sending',
            ]);
        }

        expect($this->steadyQueryCount($uri))->toBe($few);
    });
});

describe('validation', function (): void {
    it('rejects an incomplete or unsafe broadcast without saving it', function (array $overrides, string $field): void {
        actingAs($this->sender);

        $this->post(route('admin.broadcasts.store'), broadcastPayload($overrides))->assertSessionHasErrors($field);

        expect(StaffBroadcast::query()->count())->toBe(0);
    })->with([
        'no arabic title' => [['title_ar' => ''], 'title_ar'],
        'no arabic body' => [['body_ar' => ''], 'body_ar'],
        'unknown segment' => [['segment' => 'everyone'], 'segment'],
        'city without a city' => [['segment' => 'city'], 'location_id'],
        'english title without body' => [['body_en' => ''], 'body_en'],
        'markup' => [['body_ar' => '<a href="x">اضغط</a>'], 'body_ar'],
        'title too long' => [['title_ar' => str_repeat('ب', 121)], 'title_ar'],
        'body too long' => [['body_en' => str_repeat('b', 1001)], 'body_en'],
    ]);

    it('only accepts a city, not a district, as the city', function (): void {
        actingAs($this->sender);
        $district = Location::query()->where('type', LocationType::DISTRICT->value)->firstOrFail();

        $this->post(route('admin.broadcasts.store'), broadcastPayload(['segment' => 'city', 'location_id' => $district->id]))
            ->assertSessionHasErrors('location_id');
    });
});

describe('sending', function (): void {
    it('queues the broadcast, audits it and reaches every active account in the inbox only', function (): void {
        Notification::fake();
        $active = User::factory()->count(3)->create();
        $suspended = User::factory()->create(['status' => UserStatus::SUSPENDED]);
        $audience = User::query()->where('status', UserStatus::ACTIVE->value)->count();
        actingAs($this->sender);

        $this->post(route('admin.broadcasts.store'), broadcastPayload())
            ->assertRedirect(route('admin.broadcasts.index'))
            ->assertSessionHas('status', __('admin.broadcasts.flash.queued'));

        $broadcast = StaffBroadcast::query()->sole();
        // Every active account, staff included; the suspended one is skipped.
        expect($audience)->toBeGreaterThanOrEqual(4)
            ->and($broadcast->status)->toBe(BroadcastStatus::SENT)
            ->and($broadcast->sender_id)->toBe($this->sender->id)
            ->and($broadcast->recipients_count)->toBe($audience)
            ->and($broadcast->delivered_count)->toBe($audience)
            ->and($broadcast->completed_at)->not->toBeNull();

        Notification::assertSentTo($active, SystemAnnouncementNotification::class, function (SystemAnnouncementNotification $notification, array $channels) use ($broadcast): bool {
            return $notification->broadcastId === $broadcast->id && ! in_array('mail', $channels, true);
        });
        Notification::assertNotSentTo($suspended, SystemAnnouncementNotification::class);

        $audit = Activity::query()->where('event', 'admin.broadcasts.sent')->sole();
        expect($audit->causer_id)->toBe($this->sender->id)
            ->and($audit->properties['segment'])->toBe('all');
    });

    it('adds email only when staff ask for it', function (): void {
        Notification::fake();
        $user = User::factory()->create();
        actingAs($this->sender);

        $this->post(route('admin.broadcasts.store'), broadcastPayload(['send_email' => '1']));

        Notification::assertSentTo($user, SystemAnnouncementNotification::class, fn ($notification, array $channels): bool => in_array('mail', $channels, true));
    });

    it('reaches business accounts only for the businesses segment', function (): void {
        Notification::fake();
        $business = User::factory()->create(['account_type' => AccountType::BUSINESS]);
        $private = User::factory()->create(['account_type' => AccountType::PRIVATE_INDIVIDUAL]);
        actingAs($this->sender);

        $this->post(route('admin.broadcasts.store'), broadcastPayload(['segment' => 'businesses']));

        Notification::assertSentTo($business, SystemAnnouncementNotification::class);
        Notification::assertNotSentTo([$private, $this->sender], SystemAnnouncementNotification::class);
        expect(StaffBroadcast::query()->sole()->recipients_count)->toBe(1);
    });

    it('reaches sellers with a live ad in the city or its districts for the city segment', function (): void {
        Notification::fake();
        $city = broadcastCity();
        $district = $city->children()->firstOrFail();
        $elsewhere = Location::query()->where('type', LocationType::CITY->value)->whereKeyNot($city->id)->firstOrFail();

        $inCity = User::factory()->create();
        $this->makeAd($inCity, ['location_id' => $city->id, 'status' => 'active']);
        $inDistrict = User::factory()->create();
        $this->makeAd($inDistrict, ['location_id' => $district->id, 'status' => 'active']);
        $draftOnly = User::factory()->create();
        $this->makeAd($draftOnly, ['location_id' => $city->id, 'status' => 'draft']);
        $otherCity = User::factory()->create();
        $this->makeAd($otherCity, ['location_id' => $elsewhere->id, 'status' => 'active']);
        actingAs($this->sender);

        $this->post(route('admin.broadcasts.store'), broadcastPayload(['segment' => 'city', 'location_id' => $city->id]));

        Notification::assertSentTo([$inCity, $inDistrict], SystemAnnouncementNotification::class);
        Notification::assertNotSentTo([$draftOnly, $otherCity], SystemAnnouncementNotification::class);
        expect(StaffBroadcast::query()->sole())
            ->segment->toBe(BroadcastSegment::CITY)
            ->location_id->toBe($city->id)
            ->recipients_count->toBe(2);
    });

    it('gives each user the copy in their language, falling back to Arabic', function (): void {
        $arabic = User::factory()->create(['language' => Language::ARABIC]);
        $english = User::factory()->create(['language' => Language::ENGLISH]);
        $broadcast = StaffBroadcast::query()->forceCreate(['segment' => 'all', 'title_ar' => 'عنوان', 'body_ar' => 'نص', 'title_en' => 'Title', 'body_en' => 'Text', 'status' => 'queued']);
        $arabicOnly = StaffBroadcast::query()->forceCreate(['segment' => 'all', 'title_ar' => 'عنوان فقط', 'body_ar' => 'نص فقط', 'status' => 'queued']);

        expect($broadcast->toNotification()->toArray($arabic))->toMatchArray(['title' => 'عنوان', 'body' => 'نص', 'broadcast_id' => $broadcast->id])
            ->and($broadcast->toNotification()->toArray($english))->toMatchArray(['title' => 'Title', 'body' => 'Text'])
            ->and($arabicOnly->toNotification()->toArray($english))->toMatchArray(['title' => 'عنوان فقط', 'body' => 'نص فقط']);
    });
});

describe('sending limits', function (): void {
    it('caps each sender per hour', function (): void {
        Notification::fake();
        config(['qbazaar.admin.broadcasts.max_per_hour' => 2]);
        actingAs($this->sender);

        $this->post(route('admin.broadcasts.store'), broadcastPayload())->assertSessionHasNoErrors();
        $this->post(route('admin.broadcasts.store'), broadcastPayload())->assertSessionHasNoErrors();
        $this->post(route('admin.broadcasts.store'), broadcastPayload())
            ->assertSessionHasErrors(['limit' => __('admin.broadcasts.errors.hourly_limit', ['max' => 2])]);

        expect(StaffBroadcast::query()->count())->toBe(2);

        // Another sender has their own allowance.
        actingAs(User::factory()->create()->assignRole('support'));
        $this->post(route('admin.broadcasts.store'), broadcastPayload())->assertSessionHasNoErrors();

        actingAs($this->sender);
        $this->travel(61)->minutes();
        $this->post(route('admin.broadcasts.store'), broadcastPayload())->assertSessionHasNoErrors();
    });

    it('caps each sender per day', function (): void {
        Notification::fake();
        config(['qbazaar.admin.broadcasts.max_per_hour' => 5, 'qbazaar.admin.broadcasts.max_per_day' => 3]);
        actingAs($this->sender);

        foreach (range(1, 3) as $hour) {
            $this->post(route('admin.broadcasts.store'), broadcastPayload())->assertSessionHasNoErrors();
            $this->travel(2)->hours();
        }

        $this->post(route('admin.broadcasts.store'), broadcastPayload())
            ->assertSessionHasErrors(['limit' => __('admin.broadcasts.errors.daily_limit', ['max' => 3])]);

        $this->get(route('admin.broadcasts.index'))->assertSee(__('admin.broadcasts.remaining_day'));
    });
});

describe('delivery', function (): void {
    it('spreads the audience over delayed chunk jobs on the notifications queue', function (): void {
        Queue::fake();
        $this->freezeTime();
        config(['qbazaar.admin.broadcasts.chunk_size' => 2, 'qbazaar.admin.broadcasts.chunk_spacing_seconds' => 10]);
        User::factory()->count(3)->create();
        $audience = User::query()->where('status', UserStatus::ACTIVE->value)->count();
        $chunks = (int) ceil($audience / 2);
        $broadcast = StaffBroadcast::query()->forceCreate(['segment' => 'all', 'title_ar' => 'عنوان', 'body_ar' => 'نص', 'status' => 'queued']);

        app(BroadcastDelivery::class)->fanOut($broadcast);

        // One job per two users, ten seconds apart.
        Queue::assertPushed(DeliverBroadcastChunkJob::class, $chunks);
        $delays = Queue::pushed(DeliverBroadcastChunkJob::class)
            ->map(fn (DeliverBroadcastChunkJob $job): int => (int) round(now()->diffInSeconds($job->delay, true)))
            ->sort()
            ->values()
            ->all();
        expect($chunks)->toBeGreaterThanOrEqual(3)
            ->and($delays)->toBe(range(0, ($chunks - 1) * 10, 10))
            ->and(Queue::pushed(DeliverBroadcastChunkJob::class)->first()->queue)->toBe('notifications')
            ->and($broadcast->fresh())
            ->status->toBe(BroadcastStatus::SENDING)
            ->chunks_total->toBe($chunks)
            ->recipients_count->toBe($audience);
    });

    it('never fans the same broadcast out twice', function (): void {
        Queue::fake();
        $broadcast = StaffBroadcast::query()->forceCreate(['segment' => 'all', 'title_ar' => 'عنوان', 'body_ar' => 'نص', 'status' => 'queued']);

        app(BroadcastDelivery::class)->fanOut($broadcast);
        app(BroadcastDelivery::class)->fanOut($broadcast->fresh());

        Queue::assertPushed(DeliverBroadcastChunkJob::class, 1);
    });

    it('notifies nobody twice when a chunk is retried, and marks the broadcast sent after the last chunk', function (): void {
        Notification::fake();
        $users = User::factory()->count(2)->create();
        $broadcast = StaffBroadcast::query()->forceCreate(['segment' => 'all', 'title_ar' => 'عنوان', 'body_ar' => 'نص', 'status' => 'sending', 'chunks_total' => 2, 'recipients_count' => 2]);
        $delivery = app(BroadcastDelivery::class);

        $delivery->deliverChunk($broadcast, [$users[0]->id]);
        $delivery->deliverChunk($broadcast, [$users[0]->id]);
        expect($broadcast->fresh()->status)->toBe(BroadcastStatus::SENT);

        Notification::assertSentToTimes($users[0], SystemAnnouncementNotification::class, 1);
        expect($broadcast->fresh()->delivered_count)->toBe(1);
    });

    it('reports a chunk that gave up for good as failed', function (): void {
        $broadcast = StaffBroadcast::query()->forceCreate(['segment' => 'all', 'title_ar' => 'عنوان', 'body_ar' => 'نص', 'status' => 'sending']);

        (new DeliverBroadcastChunkJob($broadcast->id, []))->failed(new RuntimeException('FCM down'));

        expect($broadcast->fresh()->status)->toBe(BroadcastStatus::FAILED);

        // A late chunk of a failed broadcast does nothing.
        Notification::fake();
        app(BroadcastDelivery::class)->deliverChunk($broadcast->fresh(), [User::factory()->create()->id]);
        Notification::assertNothingSent();
    });

    it('dispatches the fan-out on the notifications queue after the broadcast is saved', function (): void {
        Queue::fake();
        actingAs($this->sender);

        $this->post(route('admin.broadcasts.store'), broadcastPayload());

        Queue::assertPushed(FanOutBroadcastJob::class, fn (FanOutBroadcastJob $job): bool => $job->queue === 'notifications'
            && $job->broadcastId === StaffBroadcast::query()->sole()->id);
    });
});
