<?php

declare(strict_types=1);

use App\Events\Messaging\ConversationRead;
use App\Events\Messaging\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
    $this->buyer = User::factory()->create();
    $this->ad = $this->makeAd($this->seller);
    $this->conversation = Conversation::query()->create([
        'ad_id' => $this->ad->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
    ]);
});

it('dispatches MessageSent with the right channels when a message is sent', function (): void {
    Event::fake([MessageSent::class]);

    Sanctum::actingAs($this->buyer, ['*']);

    postJson('/api/v1/conversations/' . $this->conversation->id . '/messages', [
        'body' => 'Realtime hello',
    ])->assertStatus(201);

    Event::assertDispatched(MessageSent::class, function (MessageSent $event): bool {
        $channelNames = collect($event->broadcastOn())
            ->map(fn (PrivateChannel $ch): string => $ch->name)
            ->all();

        return in_array('private-conversation.' . $this->conversation->id, $channelNames, true)
            && in_array('private-user.' . $this->seller->id, $channelNames, true)
            && $event->broadcastAs() === 'message.sent';
    });
});

it('dispatches ConversationRead when there is at least one unread message', function (): void {
    Event::fake([ConversationRead::class]);

    Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'sender_id' => $this->seller->id,
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->buyer, ['*']);

    postJson('/api/v1/conversations/' . $this->conversation->id . '/read')->assertOk();

    Event::assertDispatched(ConversationRead::class, function (ConversationRead $event): bool {
        $channelNames = collect($event->broadcastOn())
            ->map(fn (PrivateChannel $ch): string => $ch->name)
            ->all();

        return $event->conversationId === $this->conversation->id
            && $event->readerId === $this->buyer->id
            && in_array('private-user.' . $this->seller->id, $channelNames, true)
            && $event->broadcastAs() === 'conversation.read';
    });
});

it('does not dispatch ConversationRead when there is nothing to mark', function (): void {
    Event::fake([ConversationRead::class]);

    Sanctum::actingAs($this->buyer, ['*']);

    postJson('/api/v1/conversations/' . $this->conversation->id . '/read')->assertOk();

    Event::assertNotDispatched(ConversationRead::class);
});
