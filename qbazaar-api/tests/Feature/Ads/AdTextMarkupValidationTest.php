<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

const SCRIPT_BREAKOUT_TITLE = 'Nice car</script><script>alert(1)</script>';

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->user = User::factory()->create();
});

function validAdPayload(array $overrides = []): array
{
    return array_merge([
        'category_id' => Category::query()->leaf()->inRandomOrder()->value('id'),
        'location_id' => Location::query()->inRandomOrder()->value('id'),
        'title' => 'Vintage camera for sale',
        'description' => 'A well-kept vintage camera with original packaging and lens.',
        'price' => 100,
        'price_type' => 'fixed',
    ], $overrides);
}

it('rejects a script breakout in the title on create', function (): void {
    Sanctum::actingAs($this->user, ['*']);

    postJson('/api/v1/ads', validAdPayload(['title' => SCRIPT_BREAKOUT_TITLE]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['error' => ['details' => ['title']]]);

    expect(Ad::query()->count())->toBe(0);
});

it('rejects markup in the description on create', function (): void {
    Sanctum::actingAs($this->user, ['*']);

    postJson('/api/v1/ads', validAdPayload([
        'description' => 'Great condition <img src=x onerror=alert(1)> barely used.',
    ]))
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['details' => ['description']]]);
});

it('rejects a script breakout in the title on update', function (): void {
    Sanctum::actingAs($this->user, ['*']);
    $ad = $this->makeAd($this->user, ['title' => 'Original title']);

    putJson("/api/v1/ads/{$ad->id}", ['title' => SCRIPT_BREAKOUT_TITLE])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['details' => ['title']]]);

    expect($ad->fresh()->title)->toBe('Original title');
});

it('rejects markup in ad text edited from the admin panel', function (): void {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $ad = $this->makeAd($this->user, ['title' => 'Original title']);

    actingAs($admin)
        ->put("/admin/ads/{$ad->id}", [
            'title' => SCRIPT_BREAKOUT_TITLE,
            'description' => $ad->description,
            'category_id' => $ad->category_id,
            'location_id' => $ad->location_id,
            'price_type' => $ad->price_type->value,
            'status' => $ad->status->value,
        ])
        ->assertSessionHasErrors('title');

    expect($ad->fresh()->title)->toBe('Original title');
});
