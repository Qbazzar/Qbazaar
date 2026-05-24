<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

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

it('returns the newest messages first', function (): void {
    foreach (range(1, 5) as $i) {
        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->buyer->id,
            'body' => 'msg-' . $i,
            'created_at' => now()->addMinutes($i),
            'updated_at' => now()->addMinutes($i),
        ]);
    }

    Sanctum::actingAs($this->buyer, ['*']);

    $response = getJson('/api/v1/conversations/' . $this->conversation->id . '/messages?limit=10');

    $response->assertOk();
    $bodies = collect($response->json('data'))->pluck('body')->all();
    expect($bodies)->toBe(['msg-5', 'msg-4', 'msg-3', 'msg-2', 'msg-1']);
});

it('paginates with the before cursor', function (): void {
    $messages = [];
    foreach (range(1, 5) as $i) {
        $messages[] = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->buyer->id,
            'body' => 'msg-' . $i,
            'created_at' => now()->addMinutes($i),
            'updated_at' => now()->addMinutes($i),
        ]);
    }

    Sanctum::actingAs($this->buyer, ['*']);

    // anchor on msg-3 (the third message). Expect msg-2 then msg-1.
    $anchor = $messages[2]->id;

    $response = getJson("/api/v1/conversations/{$this->conversation->id}/messages?before={$anchor}&limit=10");

    $response->assertOk();
    $bodies = collect($response->json('data'))->pluck('body')->all();
    expect($bodies)->toBe(['msg-2', 'msg-1']);
});

it('returns 404 for non-participants to avoid leaking existence', function (): void {
    $stranger = User::factory()->create();
    Sanctum::actingAs($stranger, ['*']);

    getJson('/api/v1/conversations/' . $this->conversation->id . '/messages')
        ->assertStatus(404)
        ->assertJson(fn ($json) => $json
            ->where('error.code', 'MSG_004')
            ->etc());
});
