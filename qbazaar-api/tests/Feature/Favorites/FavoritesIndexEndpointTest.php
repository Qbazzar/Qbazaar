<?php

declare(strict_types=1);

use App\Models\Favorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);
});

it('lists the caller favourites newest first with favorited_at injected', function (): void {
    $seller = User::factory()->create();

    $adOld = $this->makeAd($seller, ['status' => 'active', 'published_at' => now()->subDays(2)]);
    $adNew = $this->makeAd($seller, ['status' => 'active', 'published_at' => now()->subDay()]);

    Favorite::query()->create([
        'user_id' => $this->user->id,
        'ad_id' => $adOld->id,
        'created_at' => now()->subHours(2),
    ]);
    Favorite::query()->create([
        'user_id' => $this->user->id,
        'ad_id' => $adNew->id,
        'created_at' => now()->subMinutes(5),
    ]);

    $response = getJson('/api/v1/account/favorites', ['Accept' => 'application/json'])
        ->assertOk();

    $data = $response->json('data');

    expect($data)->toHaveCount(2)
        ->and($data[0]['id'])->toBe($adNew->id)
        ->and($data[1]['id'])->toBe($adOld->id)
        ->and($data[0])->toHaveKey('favorited_at')
        ->and($data[0]['favorited_at'])->not->toBeNull();
});

it('does not leak other users favourites', function (): void {
    $other = User::factory()->create();
    $seller = User::factory()->create();
    $ad = $this->makeAd($seller, ['status' => 'active', 'published_at' => now()]);

    Favorite::query()->create([
        'user_id' => $other->id,
        'ad_id' => $ad->id,
        'created_at' => now(),
    ]);

    $response = getJson('/api/v1/account/favorites', ['Accept' => 'application/json'])
        ->assertOk();

    expect($response->json('data'))->toBe([]);
});
