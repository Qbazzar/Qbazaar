<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

it('appends a message and bumps the conversation preview', function (): void {
    Sanctum::actingAs($this->buyer, ['*']);

    $response = postJson('/api/v1/conversations/' . $this->conversation->id . '/messages', [
        'body' => 'Hello! Is this still available?',
    ]);

    $response->assertStatus(201)
        ->assertJson(fn ($json) => $json
            ->where('data.body', 'Hello! Is this still available?')
            ->where('data.type', 'text')
            ->where('data.sender_id', $this->buyer->id)
            ->etc());

    expect(Message::query()->count())->toBe(1);

    $this->conversation->refresh();
    expect($this->conversation->last_message_preview)->toBe('Hello! Is this still available?');
    expect($this->conversation->last_message_at)->not->toBeNull();
});

it('refuses sends when the other party is blocked', function (): void {
    $this->buyer->blockedUsers()->attach($this->seller->id, ['created_at' => now()]);

    Sanctum::actingAs($this->buyer, ['*']);

    postJson('/api/v1/conversations/' . $this->conversation->id . '/messages', [
        'body' => 'Hello',
    ])->assertStatus(403)
        ->assertJson(fn ($json) => $json->where('error.code', 'MSG_001')->etc());
});

it('returns 404 for non-participants to avoid oracle leak', function (): void {
    $stranger = User::factory()->create();
    Sanctum::actingAs($stranger, ['*']);

    postJson('/api/v1/conversations/' . $this->conversation->id . '/messages', [
        'body' => 'Hello',
    ])->assertStatus(404);
});

it('validates body length', function (): void {
    Sanctum::actingAs($this->buyer, ['*']);

    postJson('/api/v1/conversations/' . $this->conversation->id . '/messages', [
        'body' => '',
    ])->assertStatus(422);
});
