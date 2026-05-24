<?php

declare(strict_types=1);

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);
});

it('creates and lists saved searches for the current user', function (): void {
    postJson('/api/v1/account/saved-searches', [
        'name' => 'Cheap mountain bikes',
        'query_params' => [
            'q' => 'mountain bike',
            'price_max' => 1000,
        ],
    ], ['Accept' => 'application/json'])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Cheap mountain bikes');

    $response = getJson('/api/v1/account/saved-searches', ['Accept' => 'application/json'])
        ->assertOk();

    $list = $response->json('data');

    expect($list)->toHaveCount(1)
        ->and($list[0]['name'])->toBe('Cheap mountain bikes')
        ->and($list[0]['query_params']['q'])->toBe('mountain bike');
});

it('deletes a saved search owned by the current user', function (): void {
    /** @var SavedSearch $saved */
    $saved = SavedSearch::query()->create([
        'user_id' => $this->user->id,
        'name' => 'Beach apartments',
        'query_params' => ['category_slug' => 'real-estate'],
    ]);

    deleteJson("/api/v1/account/saved-searches/{$saved->id}", [], ['Accept' => 'application/json'])
        ->assertNoContent();

    expect(SavedSearch::query()->find($saved->id))->toBeNull();
});

it('returns SEARCH_SAVED_NOT_FOUND when deleting someone else\'s saved search', function (): void {
    $other = User::factory()->create();
    /** @var SavedSearch $saved */
    $saved = SavedSearch::query()->create([
        'user_id' => $other->id,
        'name' => 'Their saved search',
        'query_params' => [],
    ]);

    deleteJson("/api/v1/account/saved-searches/{$saved->id}", [], ['Accept' => 'application/json'])
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'SEARCH_002');
});

it('caps saved searches at 10 per user and surfaces SEARCH_SAVED_LIMIT on the 11th', function (): void {
    for ($i = 0; $i < 10; $i++) {
        SavedSearch::query()->create([
            'user_id' => $this->user->id,
            'name' => 'Saved #' . $i,
            'query_params' => [],
        ]);
    }

    postJson('/api/v1/account/saved-searches', [
        'name' => 'Overflow',
        'query_params' => ['q' => 'x'],
    ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'SEARCH_004');
});
