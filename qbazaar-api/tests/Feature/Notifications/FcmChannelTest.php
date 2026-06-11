<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\Ads\AdApprovedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Hash;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\ServerError;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\SendReport;
use Laravel\Sanctum\Sanctum;
use NotificationChannels\Fcm\FcmChannel;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

/**
 * Notification::fake() can't exercise channel selection, so these tests call
 * via() / toFcm() directly and drive the prune listener with a synthetic
 * NotificationFailed event shaped exactly like FcmChannel dispatches it
 * (['report' => SendReport]).
 */
beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
    $this->ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value]);
    $this->notification = new AdApprovedNotification($this->ad);
    $this->credentialsPath = null;
});

afterEach(function (): void {
    if (is_string($this->credentialsPath) && is_file($this->credentialsPath)) {
        @unlink($this->credentialsPath);
    }
});

/**
 * Points the kreait firebase config at a real (empty-JSON) temp file so the
 * "credentials file exists on disk" gate passes without real credentials.
 */
function fakeFirebaseCredentialsFile(): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'fcm');
    file_put_contents($path, '{}');

    config()->set('firebase.projects.app.credentials', $path);

    return $path;
}

it('omits the FCM channel when no credentials are configured, even with device tokens', function (): void {
    config()->set('firebase.projects.app.credentials', null);
    DeviceToken::factory()->create(['user_id' => $this->seller->id]);

    expect($this->notification->via($this->seller))
        ->toBe(['mail', 'database'])
        ->not->toContain(FcmChannel::class);
});

it('omits the FCM channel when credentials point at a missing file', function (): void {
    config()->set('firebase.projects.app.credentials', 'storage/app/definitely-not-there.json');
    DeviceToken::factory()->create(['user_id' => $this->seller->id]);

    expect($this->notification->via($this->seller))->not->toContain(FcmChannel::class);
});

it('adds the FCM channel when credentials exist and the user has device tokens', function (): void {
    $this->credentialsPath = fakeFirebaseCredentialsFile();
    DeviceToken::factory()->create(['user_id' => $this->seller->id]);

    expect($this->notification->via($this->seller))
        ->toBe(['mail', 'database', FcmChannel::class]);
});

it('omits the FCM channel for a user without device tokens', function (): void {
    $this->credentialsPath = fakeFirebaseCredentialsFile();

    expect($this->notification->via($this->seller))->not->toContain(FcmChannel::class);
});

it('builds the FCM message from the same payload toArray() persists', function (): void {
    $payload = $this->notification->toArray($this->seller);
    $message = $this->notification->toFcm($this->seller)->toArray();

    expect($message['notification']['title'])->toBe($payload['title']);
    expect($message['notification']['body'])->toBe($payload['body']);
    expect($message['data'])->toBe([
        'category' => 'ad.approved',
        'cta_url' => (string) $payload['cta_url'],
    ]);
});

it('deletes device tokens when the account is deactivated', function (): void {
    $user = User::factory()->create(['password' => Hash::make('Str0ng!Pass1')]);
    $token = DeviceToken::factory()->create(['user_id' => $user->id]);
    $otherToken = DeviceToken::factory()->create();

    Sanctum::actingAs($user, ['*']);

    postJson('/api/v1/account/deactivate', ['password' => 'Str0ng!Pass1'])
        ->assertNoContent();

    $this->assertDatabaseMissing('device_tokens', ['id' => $token->id]);
    $this->assertDatabaseHas('device_tokens', ['id' => $otherToken->id]);
});

it('prunes the device token FCM reports as unregistered and keeps the rest', function (): void {
    $stale = DeviceToken::factory()->create(['user_id' => $this->seller->id]);
    $healthy = DeviceToken::factory()->create(['user_id' => $this->seller->id]);

    $report = SendReport::failure(
        MessageTarget::with(MessageTarget::TOKEN, $stale->token),
        NotFound::becauseTokenNotFound($stale->token),
    );

    event(new NotificationFailed($this->seller, $this->notification, FcmChannel::class, ['report' => $report]));

    $this->assertDatabaseMissing('device_tokens', ['id' => $stale->id]);
    $this->assertDatabaseHas('device_tokens', ['id' => $healthy->id]);
});

it('keeps the device token on transient FCM failures', function (): void {
    $token = DeviceToken::factory()->create(['user_id' => $this->seller->id]);

    $report = SendReport::failure(
        MessageTarget::with(MessageTarget::TOKEN, $token->token),
        new ServerError('Internal server error'),
    );

    event(new NotificationFailed($this->seller, $this->notification, FcmChannel::class, ['report' => $report]));

    $this->assertDatabaseHas('device_tokens', ['id' => $token->id]);
});

it('ignores NotificationFailed events from other channels', function (): void {
    $token = DeviceToken::factory()->create(['user_id' => $this->seller->id]);

    $report = SendReport::failure(
        MessageTarget::with(MessageTarget::TOKEN, $token->token),
        NotFound::becauseTokenNotFound($token->token),
    );

    event(new NotificationFailed($this->seller, $this->notification, 'mail', ['report' => $report]));

    $this->assertDatabaseHas('device_tokens', ['id' => $token->id]);
});
