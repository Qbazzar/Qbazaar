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
});

function draftPayload(string $categoryId): array
{
    return [
        'category_id' => $categoryId,
        'location_id' => Location::query()->value('id'),
        'title' => 'Wooden dining table',
        'description' => 'Solid oak dining table with six chairs, lightly used.',
        'price' => 800,
        'price_type' => 'fixed',
    ];
}

it('accepts an active leaf category', function (): void {
    $furniture = Category::query()->where('slug', 'furniture')->value('id');

    postJson('/api/v1/ads', draftPayload($furniture))->assertCreated();
});

it('rejects a category that has sub-categories', function (): void {
    $homeAndGarden = Category::query()->where('slug', 'home-and-garden')->value('id');

    postJson('/api/v1/ads', draftPayload($homeAndGarden))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['error' => ['details' => ['category_id']]]);
});

it('rejects an inactive category and one under an inactive parent', function (): void {
    $furniture = Category::query()->where('slug', 'furniture')->firstOrFail();

    $furniture->update(['is_active' => false]);
    postJson('/api/v1/ads', draftPayload($furniture->id))->assertUnprocessable();

    $furniture->update(['is_active' => true]);
    Category::query()->whereKey($furniture->parent_id)->firstOrFail()->update(['is_active' => false]);
    postJson('/api/v1/ads', draftPayload($furniture->id))->assertUnprocessable();
});

it('rejects an unknown category', function (): void {
    postJson('/api/v1/ads', draftPayload('01JABCDEFGHJKMNPQRSTVWXYZ0'))->assertUnprocessable();
});

it('rejects moving an ad to a non-leaf category but keeps its current one', function (): void {
    $parent = Category::query()->where('slug', 'home-and-garden')->value('id');
    $ad = Ad::factory()->create([
        'user_id' => $this->user->id,
        'category_id' => $parent,
        'status' => AdStatus::DRAFT->value,
    ]);

    putJson("/api/v1/ads/{$ad->id}", ['category_id' => $parent, 'title' => 'Updated table title'])->assertOk();

    putJson("/api/v1/ads/{$ad->id}", ['category_id' => Category::query()->where('slug', 'vehicles')->value('id')])
        ->assertUnprocessable();
});
