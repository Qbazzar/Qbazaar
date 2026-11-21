<?php

declare(strict_types=1);

use App\Events\Messaging\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    Event::fake([MessageSent::class]);

    $this->seedReferenceData();
    $this->seller = User::factory()->phoneVerified()->create();
    $this->buyer = User::factory()->phoneVerified()->create();
    $this->conversation = Conversation::query()->create([
        'ad_id' => $this->makeAd($this->seller)->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
    ]);
    $this->url = '/api/v1/conversations/' . $this->conversation->id . '/messages';
});

it('replays a retried send with the same key instead of sending twice', function (): void {
    Sanctum::actingAs($this->buyer, ['*']);
    $key = ['X-Idempotency-Key' => 'msg-retry-0123456789abcdef'];

    $first = postJson($this->url, ['body' => 'Is this still available?'], $key)->assertStatus(201);
    $retry = postJson($this->url, ['body' => 'Is this still available?'], $key)->assertStatus(201);

    expect($retry->json('data.id'))->toBe($first->json('data.id'))
        ->and($retry->headers->get('X-Idempotent-Replay'))->toBe('true')
        ->and(Message::query()->where('conversation_id', $this->conversation->id)->count())->toBe(1);
    Event::assertDispatchedTimes(MessageSent::class, 1);
});

it('scopes the key to the sender', function (): void {
    $key = ['X-Idempotency-Key' => 'msg-shared-0123456789abcdef'];

    Sanctum::actingAs($this->buyer, ['*']);
    $fromBuyer = postJson($this->url, ['body' => 'Hello'], $key)->assertStatus(201);

    Sanctum::actingAs($this->seller, ['*']);
    $fromSeller = postJson($this->url, ['body' => 'Hi, yes it is'], $key)->assertStatus(201);

    expect($fromSeller->json('data.id'))->not->toBe($fromBuyer->json('data.id'))
        ->and($fromSeller->json('data.sender_id'))->toBe($this->seller->id)
        ->and(Message::query()->where('conversation_id', $this->conversation->id)->count())->toBe(2);
});

it('sends every message with a new key or without one', function (): void {
    Sanctum::actingAs($this->buyer, ['*']);

    postJson($this->url, ['body' => 'One'], ['X-Idempotency-Key' => 'msg-first-0123456789abcdef'])->assertStatus(201);
    postJson($this->url, ['body' => 'Two'], ['X-Idempotency-Key' => 'msg-second-0123456789abcd'])->assertStatus(201);
    postJson($this->url, ['body' => 'Three'])->assertStatus(201);
    postJson($this->url, ['body' => 'Three'])->assertStatus(201);

    expect(Message::query()->where('conversation_id', $this->conversation->id)->count())->toBe(4);
    Event::assertDispatchedTimes(MessageSent::class, 4);
});
