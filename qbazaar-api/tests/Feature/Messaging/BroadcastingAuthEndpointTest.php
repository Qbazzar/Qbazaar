<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

const BROADCAST_AUTH_URL = '/api/v1/broadcasting/auth';

beforeEach(function (): void {
    // The suite runs on the `null` broadcaster, which approves every channel
    // without consulting routes/channels.php. Swap in a signing driver and
    // re-register the channel rules on it so the real callbacks are exercised.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
        'broadcasting.connections.reverb.options.host' => 'localhost',
    ]);
    app(BroadcastManager::class)->forgetDrivers();
    require base_path('routes/channels.php');

    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

function requestChannelAuth(string $token, string $channel)
{
    return test()->withToken($token)->postJson(BROADCAST_AUTH_URL, [
        'socket_id' => '1234.5678',
        'channel_name' => $channel,
    ]);
}

it('authorizes a bearer token for its own user channel with a raw pusher payload', function (): void {
    requestChannelAuth($this->token, 'private-user.' . $this->user->id)
        ->assertOk()
        ->assertJsonMissingPath('success')
        ->assertJson(fn ($json) => $json->where('auth', fn (string $auth): bool => str_starts_with($auth, 'test-key:'))->etc());
});

it('denies a bearer token access to another user channel', function (): void {
    $other = User::factory()->create();

    requestChannelAuth($this->token, 'private-user.' . $other->id)->assertForbidden();
});

function makeConversationFor(User $buyer): Conversation
{
    test()->seedReferenceData();
    $seller = User::factory()->create();

    return Conversation::query()->create([
        'ad_id' => test()->makeAd($seller)->id,
        'buyer_id' => $buyer->id,
        'seller_id' => $seller->id,
    ]);
}

it('authorizes a conversation participant', function (): void {
    $conversation = makeConversationFor($this->user);

    requestChannelAuth($this->token, 'private-conversation.' . $conversation->id)
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('denies a conversation channel to a non-participant', function (): void {
    $conversation = makeConversationFor(User::factory()->create());

    requestChannelAuth($this->token, 'private-conversation.' . $conversation->id)->assertForbidden();
});

it('rejects requests without a bearer token', function (): void {
    postJson(BROADCAST_AUTH_URL, [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-user.' . $this->user->id,
    ])->assertUnauthorized();
});

it('rejects suspended users', function (): void {
    $suspended = User::factory()->suspended()->create();

    requestChannelAuth($suspended->createToken('test')->plainTextToken, 'private-user.' . $suspended->id)
        ->assertForbidden();
});
