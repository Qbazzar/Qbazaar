<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->seedReferenceData();
});

function queriesDuring(Closure $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    DB::disableQueryLog();

    return count(DB::getQueryLog());
}

function adWithImages(User $seller, int $images): Ad
{
    $ad = Ad::factory()->active()->create(['user_id' => $seller->id]);

    foreach (range(1, $images) as $position) {
        $ad->addMedia(UploadedFile::fake()->image("photo-{$position}.jpg", 20, 20))->toMediaCollection('images');
    }

    return $ad;
}

it('lists ads with a constant number of queries and the first image only', function (): void {
    $seller = User::factory()->create();
    $first = adWithImages($seller, 3);

    $fewAds = queriesDuring(fn () => getJson('/api/v1/ads')->assertOk());

    adWithImages($seller, 2);
    adWithImages($seller, 2);
    $moreAds = queriesDuring(fn () => getJson('/api/v1/ads')->assertOk());

    $listed = collect(getJson('/api/v1/ads')->json('data'))->firstWhere('id', $first->id);

    expect($moreAds)->toBe($fewAds)
        ->and($listed['primary_image']['id'])->toBe($first->getMedia('images')->sortBy('order_column')->first()?->id);
});

it('loads the inbox with a constant number of queries and correct unread counts', function (): void {
    $me = User::factory()->create();
    Sanctum::actingAs($me, ['*']);

    $startConversation = function () use ($me): Conversation {
        $seller = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'ad_id' => adWithImages($seller, 1)->id,
            'buyer_id' => $me->id,
            'seller_id' => $seller->id,
            'last_message_at' => now(),
        ]);
        Message::factory()->count(2)->create(['conversation_id' => $conversation->id, 'sender_id' => $seller->id]);
        Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $me->id]);

        return $conversation;
    };

    $startConversation();
    $fewConversations = queriesDuring(fn () => getJson('/api/v1/conversations')->assertOk());

    $startConversation();
    $startConversation();
    $moreConversations = queriesDuring(fn () => getJson('/api/v1/conversations')->assertOk());

    $rows = getJson('/api/v1/conversations')->json('data');

    expect($moreConversations)->toBe($fewConversations)
        ->and(collect($rows)->pluck('unread_count')->unique()->all())->toBe([2])
        ->and($rows[0]['ad']['thumb_url'])->not->toBeNull();
});
