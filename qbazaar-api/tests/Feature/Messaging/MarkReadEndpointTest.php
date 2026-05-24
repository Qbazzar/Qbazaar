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

it('marks all unread messages from the other party as read', function (): void {
    Message::factory()->count(3)->create([
        'conversation_id' => $this->conversation->id,
        'sender_id' => $this->seller->id,
        'read_at' => null,
    ]);
    Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'sender_id' => $this->buyer->id,
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->buyer, ['*']);

    $response = postJson('/api/v1/conversations/' . $this->conversation->id . '/read');

    $response->assertOk()
        ->assertJson(fn ($json) => $json
            ->where('data.marked', 3)
            ->etc());

    // The buyer's own message is untouched.
    $own = Message::query()->where('sender_id', $this->buyer->id)->first();
    expect($own?->read_at)->toBeNull();
});

it('refuses non-participants', function (): void {
    $stranger = User::factory()->create();
    Sanctum::actingAs($stranger, ['*']);

    postJson('/api/v1/conversations/' . $this->conversation->id . '/read')
        ->assertStatus(403);
});
