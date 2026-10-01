<?php

declare(strict_types=1);

use App\Events\Messaging\UnreadMessagesChanged;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->buyer = User::factory()->phoneVerified()->create();
    $this->seller = User::factory()->phoneVerified()->create();

    $this->conversation = Conversation::query()->create([
        'ad_id' => $this->makeAd($this->seller)->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
    ]);
});

function inboxCountersSend(User $author, string $conversationId, string $body = 'Hello'): void
{
    Sanctum::actingAs($author, ['*']);
    postJson("/api/v1/conversations/{$conversationId}/messages", ['body' => $body])->assertCreated();
}

function inboxCountersTotal(User $user): int
{
    Sanctum::actingAs($user, ['*']);

    return getJson('/api/v1/conversations/unread-count')->assertOk()->json('data.total');
}

it('opens one inbox row per participant', function (): void {
    expect(DB::table('conversation_participants')->where('conversation_id', $this->conversation->id)->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$this->buyer->id, $this->seller->id])->sort()->values()->all());
});

it('counts messages for the recipient only and clears them on read', function (): void {
    inboxCountersSend($this->seller, $this->conversation->id);
    inboxCountersSend($this->seller, $this->conversation->id);
    inboxCountersSend($this->buyer, $this->conversation->id);

    expect(inboxCountersTotal($this->buyer))->toBe(2)
        ->and(inboxCountersTotal($this->seller))->toBe(1);

    Sanctum::actingAs($this->buyer, ['*']);
    postJson("/api/v1/conversations/{$this->conversation->id}/read")->assertOk()->assertJsonPath('data.marked', 2);

    expect(inboxCountersTotal($this->buyer))->toBe(0)
        ->and(inboxCountersTotal($this->seller))->toBe(1);
});

it('keeps a message that arrives after a read counted', function (): void {
    inboxCountersSend($this->seller, $this->conversation->id);

    Sanctum::actingAs($this->buyer, ['*']);
    postJson("/api/v1/conversations/{$this->conversation->id}/read")->assertOk();
    inboxCountersSend($this->seller, $this->conversation->id);

    expect(inboxCountersTotal($this->buyer))->toBe(1);
});

it('lists the inbox newest first with per-row unread counts', function (): void {
    $older = Conversation::query()->create([
        'ad_id' => $this->makeAd($this->seller)->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
        'last_message_at' => now()->subDay(),
    ]);
    inboxCountersSend($this->seller, $this->conversation->id);

    Sanctum::actingAs($this->buyer, ['*']);
    $rows = getJson('/api/v1/conversations')->assertOk()->json('data');

    expect(array_column($rows, 'id'))->toBe([$this->conversation->id, $older->id])
        ->and(array_column($rows, 'unread_count'))->toBe([1, 0]);
});

it('loads an inbox page with a fixed number of queries', function (): void {
    foreach (range(1, 5) as $ignored) {
        $conversation = Conversation::query()->create([
            'ad_id' => $this->makeAd($this->seller)->id,
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
        ]);
        Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $this->seller->id]);
    }

    Sanctum::actingAs($this->buyer, ['*']);
    DB::enableQueryLog();
    getJson('/api/v1/conversations')->assertOk();
    $queries = collect(DB::getQueryLog())->pluck('query');

    expect($queries->filter(fn (string $sql): bool => str_contains($sql, 'from "messages"')))->toBeEmpty()
        ->and($queries->filter(fn (string $sql): bool => str_contains($sql, '"buyer_id" = ? or')))->toBeEmpty();
});

it('pushes the badge total to the user channel when it changes', function (): void {
    Event::fake([UnreadMessagesChanged::class]);

    inboxCountersSend($this->seller, $this->conversation->id);
    Event::assertDispatched(UnreadMessagesChanged::class, fn (UnreadMessagesChanged $event): bool => $event->userId === $this->buyer->id);

    Sanctum::actingAs($this->buyer, ['*']);
    postJson("/api/v1/conversations/{$this->conversation->id}/read")->assertOk();
    deleteJson('/api/v1/conversations', ['ids' => [$this->conversation->id]])->assertOk();

    Event::assertDispatchedTimes(UnreadMessagesChanged::class, 3);
});

it('broadcasts the badge total on the private user channel', function (): void {
    inboxCountersSend($this->seller, $this->conversation->id);
    $event = new UnreadMessagesChanged($this->buyer->id);

    expect($event->broadcastAs())->toBe('messages.unread')
        ->and($event->broadcastOn()[0]->name)->toBe('private-user.' . $this->buyer->id)
        ->and($event->broadcastWith())->toBe(['total' => 1]);
});
