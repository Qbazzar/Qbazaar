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
use function Pest\Laravel\putJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);
    $this->furniture = (string) Category::query()->where('slug', 'furniture')->value('id');
});

function locatedDraftPayload(string $categoryId, string $locationId): array
{
    return [
        'category_id' => $categoryId,
        'location_id' => $locationId,
        'title' => 'Wooden dining table',
        'description' => 'Solid oak dining table with six chairs, lightly used.',
        'price' => 800,
        'price_type' => 'fixed',
    ];
}

it('rejects an inactive location and one under an inactive parent', function (): void {
    $child = Location::query()->whereNotNull('parent_id')->firstOrFail();

    $child->update(['is_active' => false]);
    postJson('/api/v1/ads', locatedDraftPayload($this->furniture, $child->id))
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['details' => ['location_id']]]);

    $child->update(['is_active' => true]);
    Location::query()->whereKey($child->parent_id)->firstOrFail()->update(['is_active' => false]);
    postJson('/api/v1/ads', locatedDraftPayload($this->furniture, $child->id))->assertUnprocessable();

    Location::query()->whereKey($child->parent_id)->firstOrFail()->update(['is_active' => true]);
    postJson('/api/v1/ads', locatedDraftPayload($this->furniture, $child->id))->assertCreated();
});

it('rejects an unknown location', function (): void {
    postJson('/api/v1/ads', locatedDraftPayload($this->furniture, '01JABCDEFGHJKMNPQRSTVWXYZ0'))->assertUnprocessable();
});

it('keeps the location an ad already has after the location is deactivated', function (): void {
    $location = Location::query()->firstOrFail();
    $ad = Ad::factory()->create([
        'user_id' => $this->user->id,
        'category_id' => $this->furniture,
        'location_id' => $location->id,
        'status' => AdStatus::DRAFT->value,
    ]);
    $location->update(['is_active' => false]);

    putJson("/api/v1/ads/{$ad->id}", ['location_id' => $location->id, 'title' => 'Updated table title'])->assertOk();

    $other = Location::query()->whereKeyNot($location->id)->firstOrFail();
    $other->update(['is_active' => false]);
    putJson("/api/v1/ads/{$ad->id}", ['location_id' => $other->id])->assertUnprocessable();
});
