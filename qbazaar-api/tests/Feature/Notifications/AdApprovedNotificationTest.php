<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Events\Notifications\NotificationCreated;
use App\Models\User;
use App\Notifications\Ads\AdApprovedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
    $this->ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value]);
});

it('persists a database row when AdApproved is delivered to a User', function (): void {
    $this->seller->notify(new AdApprovedNotification($this->ad));

    $row = DB::table('notifications')
        ->where('notifiable_id', $this->seller->id)
        ->first();

    expect($row)->not->toBeNull();
    expect($row->type)->toBe(AdApprovedNotification::class);

    $data = json_decode($row->data, true);
    expect($data['category'])->toBe('ad.approved');
    expect($data['title'])->not->toBeNull();
    expect($data['body'])->not->toBeNull();
    expect($data['cta_url'])->not->toBeNull();
});

it('dispatches NotificationCreated after persistence', function (): void {
    Event::fake([NotificationCreated::class]);

    $this->seller->notify(new AdApprovedNotification($this->ad));

    Event::assertDispatched(NotificationCreated::class, function (NotificationCreated $event): bool {
        return $event->userId === $this->seller->id
            && $event->type === AdApprovedNotification::class;
    });
});

it('writes exactly one database row per notify() call', function (): void {
    expect(DB::table('notifications')->count())->toBe(0);

    $this->seller->notify(new AdApprovedNotification($this->ad));

    expect(DB::table('notifications')->count())->toBe(1);
});
