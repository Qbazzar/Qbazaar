<?php

declare(strict_types=1);

use App\Enums\AdShipping;
use App\Enums\AdStatus;
use App\Enums\AdType;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();

    $this->payload = fn (array $overrides = []): array => [
        'category_id' => Category::query()->leaf()->whereNull('custom_fields')->value('id'),
        'location_id' => Location::query()->value('id'),
        'title' => 'Vintage camera for sale',
        'description' => 'A well-kept vintage camera with original packaging and lens.',
        'price' => 1200,
        'price_type' => 'fixed',
        ...$overrides,
    ];
});

it('stores the listing details on create', function (): void {
    Sanctum::actingAs($this->seller, ['*']);

    postJson('/api/v1/ads', ($this->payload)([
        'ad_type' => 'wanted',
        'shipping' => 'delivery',
        'postal_code' => '24051',
        'street' => 'Al Sadd Street, Building 12',
        'show_full_address' => true,
    ]))
        ->assertCreated()
        ->assertJsonPath('data.ad_type', 'wanted')
        ->assertJsonPath('data.shipping', 'delivery')
        ->assertJsonPath('data.postal_code', '24051')
        ->assertJsonPath('data.street', 'Al Sadd Street, Building 12')
        ->assertJsonPath('data.show_full_address', true);

    $ad = Ad::query()->sole();
    expect($ad->ad_type)->toBe(AdType::WANTED)
        ->and($ad->shipping)->toBe(AdShipping::DELIVERY);
});

it('defaults to an offering with pickup only and a hidden address', function (): void {
    Sanctum::actingAs($this->seller, ['*']);

    postJson('/api/v1/ads', ($this->payload)())
        ->assertCreated()
        ->assertJsonPath('data.ad_type', 'offering')
        ->assertJsonPath('data.shipping', 'pickup_only')
        ->assertJsonPath('data.postal_code', null)
        ->assertJsonPath('data.show_full_address', false);
});

it('rejects unknown values and malformed address fields', function (): void {
    Sanctum::actingAs($this->seller, ['*']);

    postJson('/api/v1/ads', ($this->payload)([
        'ad_type' => 'auction',
        'shipping' => 'drone',
        'postal_code' => '<script>',
        'street' => str_repeat('a', 256),
        'show_full_address' => 'maybe',
    ]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonValidationErrors(['ad_type', 'shipping', 'postal_code', 'street', 'show_full_address'], 'error.details');
});

it('updates the listing details without sending a live ad back to review', function (): void {
    Sanctum::actingAs($this->seller, ['*']);
    $ad = $this->makeAd($this->seller, [
        'status' => AdStatus::ACTIVE->value,
        'published_at' => now()->subDay(),
        'expires_at' => now()->addDays(29),
    ]);

    putJson("/api/v1/ads/{$ad->id}", ['shipping' => 'delivery', 'postal_code' => '12345'])
        ->assertOk()
        ->assertJsonPath('data.shipping', 'delivery')
        ->assertJsonPath('data.postal_code', '12345')
        ->assertJsonPath('data.status', AdStatus::ACTIVE->value);
});

it('hides the street from the public unless the seller opts in', function (bool $showFullAddress, ?string $expected): void {
    $ad = $this->makeAd($this->seller, [
        'status' => AdStatus::ACTIVE->value,
        'published_at' => now()->subDay(),
        'expires_at' => now()->addDays(29),
        'postal_code' => '24051',
        'street' => 'Al Sadd Street',
        'show_full_address' => $showFullAddress,
    ]);

    getJson("/api/v1/ads/{$ad->id}")
        ->assertOk()
        ->assertJsonPath('data.postal_code', '24051')
        ->assertJsonPath('data.street', $expected);
})->with([
    'hidden by default' => [false, null],
    'shown when opted in' => [true, 'Al Sadd Street'],
]);

it('always shows the street to the seller', function (): void {
    Sanctum::actingAs($this->seller, ['*']);
    $ad = $this->makeAd($this->seller, ['street' => 'Al Sadd Street', 'show_full_address' => false]);

    getJson("/api/v1/ads/{$ad->id}")
        ->assertOk()
        ->assertJsonPath('data.street', 'Al Sadd Street');
});

it('puts the type, shipping and postal code in the search document but never the street', function (): void {
    $ad = $this->makeAd($this->seller, [
        'ad_type' => 'wanted',
        'shipping' => 'delivery',
        'postal_code' => '24051',
        'street' => 'Al Sadd Street',
    ])->fresh(['category', 'location']);

    $document = $ad->toSearchableArray();

    expect($document)
        ->toMatchArray(['ad_type' => 'wanted', 'shipping' => 'delivery', 'postal_code' => '24051'])
        ->not->toHaveKey('street');
});

it('validates the type and shipping search filters', function (): void {
    getJson('/api/v1/search?ad_type=auction&shipping=drone')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ad_type', 'shipping'], 'error.details');

    getJson('/api/v1/search?ad_type=wanted&shipping=delivery')->assertOk();
});
