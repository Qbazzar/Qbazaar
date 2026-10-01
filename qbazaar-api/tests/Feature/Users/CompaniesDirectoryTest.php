<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\User;
use App\Services\Users\FollowGraph;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
});

it('lists only active business accounts, most followed first', function (): void {
    $popular = User::factory()->business()->create(['full_name' => 'Popular Shop']);
    $quiet = User::factory()->business()->create(['full_name' => 'Quiet Shop']);
    User::factory()->create(['full_name' => 'Private Person']);
    User::factory()->business()->suspended()->create(['full_name' => 'Suspended Shop']);

    app(FollowGraph::class)->link(User::factory()->create(), $popular);

    getJson('/api/v1/companies')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $popular->id)
        ->assertJsonPath('data.0.followers_count', 1)
        ->assertJsonPath('data.1.id', $quiet->id)
        ->assertJsonPath('meta.total', 2);
});

it('searches by business name and account name', function (): void {
    $electronics = User::factory()->business()->create(['full_name' => 'Ahmad']);
    $electronics->businessProfile()->create(['business_name' => 'Doha Electronics', 'about' => str_repeat('Phones and laptops. ', 20)]);
    $furniture = User::factory()->business()->create(['full_name' => 'Lusail Furniture']);

    getJson('/api/v1/companies?q=electro')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $electronics->id)
        ->assertJsonPath('data.0.business_name', 'Doha Electronics')
        ->assertJsonPath('data.0.about', fn (string $about): bool => mb_strlen($about) <= 163);

    getJson('/api/v1/companies?q=lusail')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $furniture->id)
        ->assertJsonPath('data.0.business_name', 'Lusail Furniture');

    getJson('/api/v1/companies?q=nothing-matches')->assertOk()->assertJsonCount(0, 'data');
});

it('counts only active ads', function (): void {
    $company = User::factory()->business()->create();
    $this->makeAd($company, ['status' => AdStatus::ACTIVE->value, 'published_at' => now(), 'expires_at' => now()->addDays(30)]);
    $this->makeAd($company, ['status' => AdStatus::ACTIVE->value, 'published_at' => now(), 'expires_at' => now()->addDays(30)]);
    $this->makeAd($company, ['status' => AdStatus::DRAFT->value]);

    getJson('/api/v1/companies')
        ->assertOk()
        ->assertJsonPath('data.0.active_ads_count', 2);
});

it('tells a signed-in viewer which companies they follow', function (): void {
    $followed = User::factory()->business()->create();
    User::factory()->business()->create();
    $viewer = User::factory()->create();
    app(FollowGraph::class)->link($viewer, $followed);

    getJson('/api/v1/companies')->assertOk()->assertJsonPath('data.0.is_following', false);

    Sanctum::actingAs($viewer, ['*']);

    getJson('/api/v1/companies')
        ->assertOk()
        ->assertJsonPath('data.0.id', $followed->id)
        ->assertJsonPath('data.0.is_following', true)
        ->assertJsonPath('data.1.is_following', false);
});

it('paginates and validates the query', function (): void {
    config(['qbazaar.social.companies_per_page' => 2]);
    User::factory()->business()->count(3)->create();

    getJson('/api/v1/companies?page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.has_more', false);

    getJson('/api/v1/companies?q=' . str_repeat('a', 101))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['q'], 'error.details');
});

it('loads a page with a fixed number of queries', function (): void {
    foreach (User::factory()->business()->count(5)->create() as $company) {
        $company->businessProfile()->create(['business_name' => 'Shop ' . $company->id]);
    }

    DB::enableQueryLog();
    getJson('/api/v1/companies')->assertOk()->assertJsonCount(5, 'data');

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(5);
});
