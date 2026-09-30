<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->phoneVerified()->create();
    $this->buyer = User::factory()->create(['phone_verified' => false]);
    $this->ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value]);
    $this->conversation = Conversation::query()->create([
        'ad_id' => $this->ad->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
    ]);

    Sanctum::actingAs($this->buyer, ['*']);
});

function expectPhoneNotVerified(string $uri, array $payload = []): void
{
    postJson($uri, $payload)
        ->assertForbidden()
        ->assertJsonPath('error.code', 'AUTH_003');
}

it('blocks publishing an ad until the phone is verified', function (): void {
    $draft = $this->makeAd($this->buyer, ['status' => AdStatus::DRAFT->value]);

    expectPhoneNotVerified("/api/v1/ads/{$draft->id}/publish");

    expect($draft->fresh()->status)->toBe(AdStatus::DRAFT);
});

it('blocks starting a conversation until the phone is verified', function (): void {
    expectPhoneNotVerified('/api/v1/conversations', ['ad_id' => $this->ad->id]);
});

it('blocks sending a message until the phone is verified', function (): void {
    expectPhoneNotVerified("/api/v1/conversations/{$this->conversation->id}/messages", ['body' => 'Hello']);

    expect($this->conversation->messages()->count())->toBe(0);
});

it('blocks making an offer until the phone is verified', function (): void {
    expectPhoneNotVerified("/api/v1/conversations/{$this->conversation->id}/offers", ['amount' => 1500]);
});

it('lets a verified user through the same gate', function (): void {
    $this->buyer->forceFill(['phone_verified' => true])->save();

    postJson("/api/v1/conversations/{$this->conversation->id}/messages", ['body' => 'Hello'])
        ->assertCreated();
});
