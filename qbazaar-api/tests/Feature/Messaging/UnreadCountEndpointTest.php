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
    $this->buyer = User::factory()->create();
    $this->sellerA = User::factory()->create();
    $this->sellerB = User::factory()->create();

    $adA = $this->makeAd($this->sellerA);
    $adB = $this->makeAd($this->sellerB);

    $this->convA = Conversation::query()->create([
        'ad_id' => $adA->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->sellerA->id,
    ]);
    $this->convB = Conversation::query()->create([
        'ad_id' => $adB->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->sellerB->id,
    ]);
});

it('sums unread messages across all conversations the caller participates in', function (): void {
    // 2 unread from sellerA + 3 unread from sellerB = 5
    Message::factory()->count(2)->create([
        'conversation_id' => $this->convA->id,
        'sender_id' => $this->sellerA->id,
        'read_at' => null,
    ]);
    Message::factory()->count(3)->create([
        'conversation_id' => $this->convB->id,
        'sender_id' => $this->sellerB->id,
        'read_at' => null,
    ]);
    // Buyer's own messages don't count.
    Message::factory()->count(4)->create([
        'conversation_id' => $this->convA->id,
        'sender_id' => $this->buyer->id,
        'read_at' => null,
    ]);
    // Already-read messages don't count.
    Message::factory()->create([
        'conversation_id' => $this->convB->id,
        'sender_id' => $this->sellerB->id,
        'read_at' => now(),
    ]);

    Sanctum::actingAs($this->buyer, ['*']);

    $response = getJson('/api/v1/conversations/unread-count');

    $response->assertOk()
        ->assertJson(fn ($json) => $json
            ->where('data.total', 5)
            ->etc());
});

it('returns zero when the caller has no conversations', function (): void {
    $other = User::factory()->create();
    Sanctum::actingAs($other, ['*']);

    getJson('/api/v1/conversations/unread-count')
        ->assertOk()
        ->assertJson(fn ($json) => $json->where('data.total', 0)->etc());
});
