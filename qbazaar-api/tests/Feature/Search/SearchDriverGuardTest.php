<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    config(['scout.driver' => 'collection']);

    $this->seedReferenceData();

    Ad::factory()->active()->create([
        'user_id' => User::factory()->create()->id,
        'title' => 'Mountain bike for sale',
    ]);
});

it('returns an empty page instead of failing when search runs on a non-meilisearch driver', function (): void {
    getJson('/api/v1/search?q=mountain', ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.total', 0);
});

it('returns no suggestions instead of failing when search runs on a non-meilisearch driver', function (): void {
    getJson('/api/v1/search/suggestions?q=mount', ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data', []);
});
