<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Conversation;
use App\Models\Favorite;
use App\Models\Message;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

/**
 * Inserts a database-channel notification row directly so the test owns the
 * read/unread state without standing up a real Notification instance.
 */
function insertSummaryNotification(User $user, ?DateTimeInterface $readAt): void
{
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\Ads\\AdApprovedNotification',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->getKey(),
        'data' => json_encode(['category' => 'ad.approved', 'title' => 'x', 'body' => 'y']),
        'read_at' => $readAt,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);
});

it('counts the caller ads, drafts, conversations, unread notifications and favourites', function (): void {
    // Ads owned by the caller: 3 non-draft ("my ads") + 1 draft (counted apart).
    $this->makeAd($this->user, ['status' => AdStatus::ACTIVE->value, 'published_at' => now()]);
    $this->makeAd($this->user, ['status' => AdStatus::ACTIVE->value, 'published_at' => now()]);
    $this->makeAd($this->user, ['status' => AdStatus::SOLD->value, 'published_at' => now()->subDay()]);
    $this->makeAd($this->user, ['status' => AdStatus::DRAFT->value]);

    // Conversations: one where the caller is the buyer, one where they are the seller.
    $otherSeller = User::factory()->create();
    $otherBuyer = User::factory()->create();
    $sellerAd = $this->makeAd($otherSeller, ['status' => AdStatus::ACTIVE->value, 'published_at' => now()]);
    $ownAd = $this->makeAd($this->user, ['status' => AdStatus::ACTIVE->value, 'published_at' => now()]);

    Conversation::query()->create([
        'ad_id' => $sellerAd->id,
        'buyer_id' => $this->user->id,
        'seller_id' => $otherSeller->id,
    ]);
    $incoming = Conversation::query()->create([
        'ad_id' => $ownAd->id,
        'buyer_id' => $otherBuyer->id,
        'seller_id' => $this->user->id,
    ]);

    // Unread messages: two from the buyer; the caller's own reply and a read one don't count.
    Message::factory()->count(2)->create(['conversation_id' => $incoming->id, 'sender_id' => $otherBuyer->id]);
    Message::factory()->create(['conversation_id' => $incoming->id, 'sender_id' => $otherBuyer->id, 'read_at' => now()]);
    Message::factory()->create(['conversation_id' => $incoming->id, 'sender_id' => $this->user->id]);

    SavedSearch::query()->create(['user_id' => $this->user->id, 'name' => 'Phones', 'query_params' => ['q' => 'phone']]);

    // Notifications: 2 unread + 1 read.
    insertSummaryNotification($this->user, null);
    insertSummaryNotification($this->user, null);
    insertSummaryNotification($this->user, now());

    Favorite::query()->create([
        'user_id' => $this->user->id,
        'ad_id' => $sellerAd->id,
    ]);

    getJson('/api/v1/account/summary')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.my_ads', 4)
        ->assertJsonPath('data.drafts', 1)
        ->assertJsonPath('data.conversations', 2)
        ->assertJsonPath('data.unread_notifications', 2)
        ->assertJsonPath('data.favorites', 1)
        ->assertJsonPath('data.unread_messages', 2)
        ->assertJsonPath('data.saved_searches', 1)
        ->assertJsonPath('data.ads_by_status', [
            'draft' => 1,
            'pending' => 0,
            'active' => 3,
            'rejected' => 0,
            'sold' => 1,
            'expired' => 0,
            'blocked' => 0,
        ]);
});

it('returns zeroed counters for a brand-new account', function (): void {
    getJson('/api/v1/account/summary')
        ->assertOk()
        ->assertJsonPath('data.my_ads', 0)
        ->assertJsonPath('data.drafts', 0)
        ->assertJsonPath('data.conversations', 0)
        ->assertJsonPath('data.unread_notifications', 0)
        ->assertJsonPath('data.favorites', 0)
        ->assertJsonPath('data.unread_messages', 0)
        ->assertJsonPath('data.saved_searches', 0)
        ->assertJsonPath('data.ads_by_status.active', 0);
});

it('rejects unauthenticated callers', function (): void {
    $this->refreshApplication();

    getJson('/api/v1/account/summary')
        ->assertStatus(401);
});
