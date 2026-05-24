<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->user = User::factory()->create();
});

it('creates a draft ad when payload is valid', function (): void {
    Sanctum::actingAs($this->user, ['*']);

    $payload = [
        'category_id' => Category::query()->inRandomOrder()->value('id'),
        'location_id' => Location::query()->inRandomOrder()->value('id'),
        'title' => 'Vintage camera for sale',
        'description' => 'A well-kept vintage camera with original packaging and lens.',
        'price' => 1200.50,
        'price_type' => 'fixed',
        'condition' => 'like_new',
    ];

    $response = postJson('/api/v1/ads', $payload, [
        'Accept' => 'application/json',
    ]);

    $response->assertCreated()
        ->assertJson(
            fn ($json) => $json
                ->where('success', true)
                ->where('data.status', AdStatus::DRAFT->value)
                ->where('data.title', 'Vintage camera for sale')
                ->etc(),
        );

    expect(Ad::query()->where('user_id', $this->user->id)->count())->toBe(1);
});

it('rejects unauthenticated callers with 401', function (): void {
    postJson('/api/v1/ads', [
        'title' => 'x',
    ], [
        'Accept' => 'application/json',
    ])->assertStatus(401);
});

it('returns 422 when required fields are missing', function (): void {
    Sanctum::actingAs($this->user, ['*']);

    postJson('/api/v1/ads', [], [
        'Accept' => 'application/json',
    ])->assertStatus(422);
});

it('forces price to null for free price type', function (): void {
    Sanctum::actingAs($this->user, ['*']);

    $payload = [
        'category_id' => Category::query()->inRandomOrder()->value('id'),
        'location_id' => Location::query()->inRandomOrder()->value('id'),
        'title' => 'Free items giveaway',
        'description' => 'Take these items off my hands, free of charge for pickup.',
        'price' => 999,
        'price_type' => 'free',
    ];

    $response = postJson('/api/v1/ads', $payload, [
        'Accept' => 'application/json',
    ]);

    $response->assertCreated()
        ->assertJson(
            fn ($json) => $json
                ->where('success', true)
                ->where('data.price', null)
                ->where('data.price_type', 'free')
                ->etc(),
        );
});
