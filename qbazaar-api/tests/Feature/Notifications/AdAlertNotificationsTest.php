<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\Language;
use App\Enums\UserStatus;
use App\Events\Ads\AdPriceDropped;
use App\Events\Ads\AdPublished;
use App\Models\Favorite;
use App\Models\Follow;
use App\Models\User;
use App\Notifications\Ads\AdPriceDroppedNotification;
use App\Notifications\Ads\NewAdFromFollowedSellerNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
    $this->ad = $this->makeAd($this->seller, [
        'status' => AdStatus::ACTIVE->value,
        'price' => 1000,
        'price_type' => 'fixed',
        'published_at' => now(),
    ]);
});

function favoriteBy(User $user, object $test): void
{
    Favorite::query()->create(['user_id' => $user->id, 'ad_id' => $test->ad->id, 'created_at' => now()]);
}

it('alerts favouriters when a live ad gets cheaper', function (): void {
    Notification::fake();

    $fan = User::factory()->create(['language' => Language::ARABIC]);
    $blocker = User::factory()->create();
    $suspended = User::factory()->create(['status' => UserStatus::SUSPENDED]);
    foreach ([$fan, $blocker, $suspended] as $user) {
        favoriteBy($user, $this);
    }
    $blocker->blockedUsers()->attach($this->seller->id, ['created_at' => now()]);

    Sanctum::actingAs($this->seller, ['*']);
    putJson("/api/v1/ads/{$this->ad->id}", ['price' => 800])->assertOk();

    Notification::assertSentTo($fan, AdPriceDroppedNotification::class, function (AdPriceDroppedNotification $notification) use ($fan): bool {
        $payload = $notification->toArray($fan);

        return $payload['category'] === 'ad.price_changed'
            && $payload['previous_price'] === '1000.00'
            && $payload['price'] === '800.00'
            && str_contains($payload['body'], '800.00');
    });
    Notification::assertNotSentTo([$blocker, $suspended, $this->seller], AdPriceDroppedNotification::class);
});

it('stays quiet on price increases', function (): void {
    Notification::fake();
    favoriteBy(User::factory()->create(), $this);

    Sanctum::actingAs($this->seller, ['*']);
    putJson("/api/v1/ads/{$this->ad->id}", ['price' => 1200])->assertOk();

    Notification::assertNothingSent();
});

it('alerts each favouriter once per price even when the event repeats', function (): void {
    Notification::fake();
    $fan = User::factory()->create();
    favoriteBy($fan, $this);
    $this->ad->forceFill(['price' => 900])->save();

    AdPriceDropped::dispatch($this->ad, '1000.00', '900.00');
    AdPriceDropped::dispatch($this->ad, '1000.00', '900.00');

    Notification::assertSentToTimes($fan, AdPriceDroppedNotification::class, 1);
});

it('fans a new ad out to the seller followers only', function (): void {
    Notification::fake();
    config(['qbazaar.notifications.fan_out_chunk' => 1]);

    $followers = User::factory()->count(2)->create();
    $stranger = User::factory()->create();
    foreach ($followers as $follower) {
        Follow::query()->create(['follower_id' => $follower->id, 'followed_id' => $this->seller->id, 'created_at' => now()]);
    }

    AdPublished::dispatch($this->ad);

    foreach ($followers as $follower) {
        Notification::assertSentTo($follower, NewAdFromFollowedSellerNotification::class, fn ($notification): bool => $notification->toArray($follower)['category'] === 'ads.new_from_followed'
            && $notification->toArray($follower)['seller_id'] === $this->seller->id);
    }
    Notification::assertNotSentTo([$stranger, $this->seller], NewAdFromFollowedSellerNotification::class);
});

it('stores the category in its own column and filters the inbox by it', function (): void {
    $user = User::factory()->create();
    $user->notify(new AdPriceDroppedNotification($this->ad->load('user'), '1000.00', '900.00'));
    $user->notify(new NewAdFromFollowedSellerNotification($this->ad));

    expect(DB::table('notifications')->pluck('category')->sort()->values()->all())
        ->toBe(['ad.price_changed', 'ads.new_from_followed']);

    Sanctum::actingAs($user, ['*']);

    getJson('/api/v1/account/notifications?category=ad.price_changed')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.category', 'ad.price_changed');

    getJson('/api/v1/account/notifications?category=ad')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    getJson('/api/v1/account/notifications?category=ads')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.category', 'ads.new_from_followed');

    getJson('/api/v1/account/notifications')->assertOk()->assertJsonCount(2, 'data');

    getJson('/api/v1/account/notifications?category=' . urlencode("ad' OR 1=1"))
        ->assertStatus(422);
});

it('backfills the category of existing notifications', function (): void {
    $user = User::factory()->create();
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'legacy',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id,
        'data' => json_encode(['category' => 'search.match']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_10_01_200400_add_category_to_notifications_table.php');
    $migration->down();
    $migration->up();

    expect(DB::table('notifications')->value('category'))->toBe('search.match');
});
