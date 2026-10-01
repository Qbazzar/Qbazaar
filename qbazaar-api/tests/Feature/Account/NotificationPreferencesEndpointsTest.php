<?php

declare(strict_types=1);

use App\Data\Account\NotificationPreferences;
use App\Enums\NotificationTopic;
use App\Models\Ad;
use App\Models\Message;
use App\Models\User;
use App\Notifications\Ads\AdApprovedNotification;
use App\Notifications\Ads\AdPriceDroppedNotification;
use App\Notifications\Ads\NewAdFromFollowedSellerNotification;
use App\Notifications\Messaging\NewMessagePushNotification;
use App\Notifications\Search\SavedSearchMatchNotification;
use App\Notifications\SystemAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use NotificationChannels\Fcm\FcmChannel;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);
});

function allTopics(bool $value): array
{
    return array_fill_keys(NotificationTopic::values(), $value);
}

it('returns every topic switched on by default', function (): void {
    getJson('/api/v1/account/notification-preferences')
        ->assertOk()
        ->assertExactJson(['success' => true, 'data' => ['email' => allTopics(true), 'push' => allTopics(true)]]);
});

it('replaces every switch with PUT', function (): void {
    $push = [...allTopics(true), 'newsletters' => false];

    putJson('/api/v1/account/notification-preferences', ['email' => allTopics(false), 'push' => $push])
        ->assertOk()
        ->assertJsonPath('data.email', allTopics(false))
        ->assertJsonPath('data.push', $push);

    expect($this->user->fresh()->notification_preferences->toArray())
        ->toBe(['email' => allTopics(false), 'push' => $push]);
});

it('requires every topic on PUT and rejects unknown ones', function (): void {
    putJson('/api/v1/account/notification-preferences', [
        'email' => ['user_messages' => false, 'casino' => true],
        'push' => allTopics(true),
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonValidationErrors(['email', 'email.offers'], 'error.details');
});

it('shows the email switches on their own', function (): void {
    getJson('/api/v1/account/email-preferences')
        ->assertOk()
        ->assertExactJson(['success' => true, 'data' => allTopics(true)]);
});

it('patches one email switch and leaves the rest, push included, untouched', function (): void {
    patchJson('/api/v1/account/email-preferences', ['newsletters' => false])
        ->assertOk()
        ->assertJsonPath('data.newsletters', false)
        ->assertJsonPath('data.user_messages', true);

    $stored = $this->user->fresh()->notification_preferences->toArray();

    expect($stored['email'])->toBe([...allTopics(true), 'newsletters' => false])
        ->and($stored['push'])->toBe(allTopics(true));
});

it('rejects an empty or non-boolean email patch', function (): void {
    patchJson('/api/v1/account/email-preferences', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['preferences'], 'error.details');

    patchJson('/api/v1/account/email-preferences', ['newsletters' => 'maybe'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['newsletters'], 'error.details');
});

it('requires authentication', function (): void {
    $this->refreshApplication();

    getJson('/api/v1/account/notification-preferences')->assertStatus(401);
    patchJson('/api/v1/account/email-preferences', ['newsletters' => false])->assertStatus(401);
});

it('drops mail when the topic email switch is off and keeps the inbox', function (): void {
    $notification = new AdApprovedNotification(new Ad);

    expect($notification->via($this->user))->toContain('mail', 'database');

    $this->user->forceFill([
        'notification_preferences' => NotificationPreferences::defaults()->with(email: ['listing_updates' => false]),
    ])->save();

    expect($notification->via($this->user->fresh()))->toBe(['database']);
});

it('drops push when the topic push switch is off', function (): void {
    config()->set('firebase.projects.' . config('firebase.default', 'app') . '.credentials', '{"type":"service_account"}');
    $this->user->deviceTokens()->create(['token' => str_repeat('a', 40), 'platform' => 'web']);

    $notification = new SystemAnnouncementNotification('Title', 'Body');

    expect($notification->via($this->user))->toBe(['mail', 'database', FcmChannel::class]);

    $this->user->forceFill([
        'notification_preferences' => NotificationPreferences::defaults()->with(push: ['newsletters' => false]),
    ])->save();

    expect($notification->via($this->user->fresh()))->toBe(['mail', 'database']);
});

it('skips the device-token lookup for a chat push the user muted', function (): void {
    config()->set('firebase.projects.' . config('firebase.default', 'app') . '.credentials', '{"type":"service_account"}');
    $this->user->deviceTokens()->create(['token' => str_repeat('a', 40), 'platform' => 'web']);

    $notification = new NewMessagePushNotification(new Message, User::factory()->make());

    expect($notification->via($this->user))->toBe([FcmChannel::class]);

    $this->user->forceFill([
        'notification_preferences' => NotificationPreferences::defaults()->with(push: ['user_messages' => false]),
    ])->save();
    $muted = $this->user->fresh();

    DB::enableQueryLog();
    $channels = $notification->via($muted);

    expect($channels)->toBe([])
        ->and(DB::getQueryLog())->toBe([]);
});

it('keeps ad alerts in the inbox but drops their push when watched-ad alerts are muted', function (): void {
    config()->set('firebase.projects.' . config('firebase.default', 'app') . '.credentials', '{"type":"service_account"}');
    $this->user->deviceTokens()->create(['token' => str_repeat('a', 40), 'platform' => 'web']);

    $notifications = [
        new AdPriceDroppedNotification(new Ad, '100.00', '80.00'),
        new NewAdFromFollowedSellerNotification(new Ad),
        new SavedSearchMatchNotification(new Ad, 'Bikes'),
    ];

    foreach ($notifications as $notification) {
        expect($notification->via($this->user))->toBe(['database', FcmChannel::class]);
    }

    $this->user->forceFill([
        'notification_preferences' => NotificationPreferences::defaults()->with(push: ['saved_search_alerts' => false]),
    ])->save();
    $muted = $this->user->fresh();

    DB::enableQueryLog();

    foreach ($notifications as $notification) {
        expect($notification->via($muted))->toBe(['database']);
    }

    expect(DB::getQueryLog())->toBe([]);
});
