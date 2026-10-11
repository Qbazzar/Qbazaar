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

it('answers from the database instead of failing when search runs on a non-meilisearch driver', function (): void {
    getJson('/api/v1/search?q=mountain', ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Mountain bike for sale')
        ->assertJsonPath('meta.total', 1);
});

it('returns no suggestions instead of failing when search runs on a non-meilisearch driver', function (): void {
    getJson('/api/v1/search/suggestions?q=mount', ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('does not scan for a keyword on a production database unless the fallback is switched on', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    getJson('/api/v1/search?q=mountain')->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
    getJson('/api/v1/ads?q=mountain')->assertOk()->assertJsonPath('data', []);

    config(['qbazaar.search.db_keyword_fallback' => true]);

    getJson('/api/v1/search?q=mountain')->assertOk()->assertJsonPath('meta.total', 1);
});

it('still lists without a keyword on a production database', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    getJson('/api/v1/ads')->assertOk()->assertJsonPath('meta.total', 1);
});
