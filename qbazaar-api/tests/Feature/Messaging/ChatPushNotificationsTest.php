<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Conversation;
use App\Models\DeviceToken;
use App\Models\Offer;
use App\Models\User;
use App\Notifications\Messaging\NewMessagePushNotification;
use App\Notifications\Messaging\OfferPushNotification;
use App\Services\Messaging\RealtimePresence;
use App\Services\Offers\OfferTransitionService;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use NotificationChannels\Fcm\FcmChannel;

use function Pest\Laravel\postJson;

use Pusher\Pusher;
use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->phoneVerified()->create(['full_name' => 'Sara Seller', 'language' => 'en']);
    $this->buyer = User::factory()->phoneVerified()->create(['full_name' => 'Bilal Buyer', 'language' => 'en']);
    $this->ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value, 'title' => 'Road bike']);
    $this->conversation = Conversation::query()->create([
        'ad_id' => $this->ad->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
    ]);

    DeviceToken::factory()->create(['user_id' => $this->seller->id]);
    DeviceToken::factory()->create(['user_id' => $this->buyer->id]);

    // The FCM channel is only selected when the firebase credentials file exists.
    $this->credentialsPath = (string) tempnam(sys_get_temp_dir(), 'fcm');
    file_put_contents($this->credentialsPath, '{}');
    config()->set('firebase.projects.app.credentials', $this->credentialsPath);

    Notification::fake();
});

afterEach(function (): void {
    @unlink($this->credentialsPath);
});

function markUsersOnline(): void
{
    app()->instance(RealtimePresence::class, new class extends RealtimePresence
    {
        public function isOnline(string $userId): bool
        {
            return true;
        }
    });
}

function sendChatMessage(User $sender, Conversation $conversation, string $body): void
{
    Sanctum::actingAs($sender, ['*']);

    postJson("/api/v1/conversations/{$conversation->id}/messages", ['body' => $body])->assertCreated();
}

function pendingOfferFor(Conversation $conversation): Offer
{
    return Offer::factory()->pending()->create([
        'conversation_id' => $conversation->id,
        'ad_id' => $conversation->ad_id,
        'buyer_id' => $conversation->buyer_id,
        'seller_id' => $conversation->seller_id,
        'amount' => 800,
    ]);
}

function fakePusherBroadcaster(Closure $channelInfo): void
{
    $pusher = Mockery::mock(Pusher::class);
    $pusher->shouldReceive('getChannelInfo')->with('private-user.user-1')->andReturnUsing($channelInfo);

    $broadcaster = Mockery::mock(PusherBroadcaster::class);
    $broadcaster->shouldReceive('getPusher')->andReturn($pusher);

    Broadcast::shouldReceive('connection')->andReturn($broadcaster);
}

it('pushes a new message to the offline recipient only', function (): void {
    sendChatMessage($this->buyer, $this->conversation, 'Is the bike still available?');

    Notification::assertSentTo(
        $this->seller,
        NewMessagePushNotification::class,
        function (NewMessagePushNotification $notification, array $channels): bool {
            $data = $notification->toFcm($this->seller)->toArray()['data'];

            return $channels === [FcmChannel::class]
                && $data['title'] === 'New message from Bilal Buyer'
                && $data['body'] === 'Is the bike still available?'
                && $data['category'] === 'message.new'
                && str_ends_with($data['cta_url'], '/account/messages?c=' . $this->conversation->id);
        },
    );
    Notification::assertNotSentTo($this->buyer, NewMessagePushNotification::class);
});

it('skips the push when the recipient has the app open', function (): void {
    markUsersOnline();

    sendChatMessage($this->buyer, $this->conversation, 'Hello');

    Notification::assertNothingSent();
});

it('skips the push when the recipient has no device token', function (): void {
    DeviceToken::query()->where('user_id', $this->seller->id)->delete();

    sendChatMessage($this->buyer, $this->conversation, 'Hello');

    Notification::assertNothingSent();
});

it('skips the push when firebase is not configured', function (): void {
    config()->set('firebase.projects.app.credentials', null);

    sendChatMessage($this->buyer, $this->conversation, 'Hello');

    Notification::assertNothingSent();
});

it('pushes a new offer to the seller without a duplicate message push', function (): void {
    Sanctum::actingAs($this->buyer, ['*']);

    postJson("/api/v1/conversations/{$this->conversation->id}/offers", ['amount' => 1500])->assertCreated();

    Notification::assertSentTo(
        $this->seller,
        OfferPushNotification::class,
        function (OfferPushNotification $notification): bool {
            $data = $notification->toFcm($this->seller)->toArray()['data'];

            return $data['category'] === 'offer.created'
                && $data['body'] === 'You received an offer of 1500.00 QAR on "Road bike".';
        },
    );
    Notification::assertNotSentTo($this->seller, NewMessagePushNotification::class);
    Notification::assertNothingSentTo($this->buyer);
});

it('pushes the outcome of an offer to the side that proposed it', function (string $action, string $actor, string $recipient, string $category): void {
    $offer = pendingOfferFor($this->conversation);
    Sanctum::actingAs($this->{$actor}, ['*']);

    postJson("/api/v1/offers/{$offer->id}/{$action}")->assertSuccessful();

    Notification::assertSentTo(
        $this->{$recipient},
        OfferPushNotification::class,
        fn (OfferPushNotification $notification): bool => $notification->category === $category,
    );
    Notification::assertNothingSentTo($this->{$actor});
})->with([
    'accepted' => ['accept', 'seller', 'buyer', 'offer.accepted'],
    'rejected' => ['reject', 'seller', 'buyer', 'offer.rejected'],
    'withdrawn' => ['withdraw', 'buyer', 'seller', 'offer.withdrawn'],
]);

it('pushes a counter-offer to the buyer', function (): void {
    $offer = pendingOfferFor($this->conversation);
    Sanctum::actingAs($this->seller, ['*']);

    postJson("/api/v1/offers/{$offer->id}/counter", ['amount' => 950])->assertCreated();

    Notification::assertSentTo(
        $this->buyer,
        OfferPushNotification::class,
        fn (OfferPushNotification $notification): bool => $notification->category === 'offer.countered',
    );
    Notification::assertNothingSentTo($this->seller);
});

it('pushes an expired offer even after its ad was deleted', function (): void {
    $offer = pendingOfferFor($this->conversation);
    $this->ad->delete();

    app(OfferTransitionService::class)->expireOpenOffersOnAd($this->ad->id);

    Notification::assertSentTo(
        $this->buyer,
        OfferPushNotification::class,
        function (OfferPushNotification $notification): bool {
            $data = $notification->toArray($this->buyer);

            return $data['category'] === 'offer.expired'
                && str_contains($data['body'], 'Road bike');
        },
    );
    expect($offer->fresh()?->status->value)->toBe('expired');
});

describe('realtime presence', function (): void {
    it('reports a user with an occupied personal channel as online', function (): void {
        fakePusherBroadcaster(fn () => (object) ['occupied' => true]);

        expect(app(RealtimePresence::class)->isOnline('user-1'))->toBeTrue();
    });

    it('treats an unreachable realtime server as offline', function (): void {
        fakePusherBroadcaster(fn () => throw new RuntimeException('Connection refused'));

        expect(app(RealtimePresence::class)->isOnline('user-1'))->toBeFalse();
    });

    it('treats everyone as offline when the online check is disabled', function (): void {
        config()->set('qbazaar.messaging.push_skip_online_recipients', false);
        fakePusherBroadcaster(fn () => (object) ['occupied' => true]);

        expect(app(RealtimePresence::class)->isOnline('user-1'))->toBeFalse();
    });
});
