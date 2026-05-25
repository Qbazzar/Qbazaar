<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Conversation;
use App\Models\Offer;
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
    $this->ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value]);
    $this->conversation = Conversation::query()->create([
        'ad_id' => $this->ad->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
    ]);
});

it('lists offers newest first for participants', function (): void {
    Offer::factory()->create([
        'conversation_id' => $this->conversation->id,
        'ad_id' => $this->ad->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
        'amount' => 100,
        'created_at' => now()->subMinutes(5),
    ]);

    Offer::factory()->create([
        'conversation_id' => $this->conversation->id,
        'ad_id' => $this->ad->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
        'amount' => 200,
        'created_at' => now(),
    ]);

    Sanctum::actingAs($this->buyer, ['*']);

    $response = getJson('/api/v1/conversations/' . $this->conversation->id . '/offers');

    $response->assertStatus(200)
        ->assertJson(fn ($json) => $json
            ->where('success', true)
            ->has('data', 2)
            ->where('data.0.amount', '200.00')
            ->where('data.1.amount', '100.00')
            ->etc());
});

it('returns 404 for non-participants', function (): void {
    $stranger = User::factory()->create();
    Sanctum::actingAs($stranger, ['*']);

    getJson('/api/v1/conversations/' . $this->conversation->id . '/offers')
        ->assertStatus(404);
});
