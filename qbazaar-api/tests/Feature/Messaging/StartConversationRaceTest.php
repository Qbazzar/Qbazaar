<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

it('returns the conversation a concurrent request created instead of failing', function (): void {
    $this->seedReferenceData();
    $seller = User::factory()->phoneVerified()->create();
    $buyer = User::factory()->phoneVerified()->create();
    $ad = $this->makeAd($seller, ['status' => AdStatus::ACTIVE->value]);
    $concurrentId = (string) Str::ulid();

    // The other request's insert lands after our lookup and before our own insert.
    Conversation::creating(function () use ($ad, $buyer, $seller, $concurrentId): void {
        DB::table('conversations')->insertOrIgnore([
            'id' => $concurrentId,
            'ad_id' => $ad->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    Sanctum::actingAs($buyer, ['*']);

    postJson('/api/v1/conversations', ['ad_id' => $ad->id])
        ->assertOk()
        ->assertJsonPath('data.id', $concurrentId);

    expect(Conversation::query()->count())->toBe(1);
});
