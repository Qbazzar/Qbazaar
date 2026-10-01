<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
    $this->cars = Category::query()->where('slug', 'cars')->firstOrFail();
});

function categoryAdsCount(string $slug): int
{
    return (int) getJson("/api/v1/categories/{$slug}/stats")->assertOk()->json('data.ads_count');
}

it('moves the count when an ad changes category', function (): void {
    $ad = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'category_id' => $this->cars->id]);
    $furniture = Category::query()->where('slug', 'furniture')->firstOrFail();

    expect(categoryAdsCount('cars'))->toBe(1)
        ->and(categoryAdsCount('furniture'))->toBe(0);

    $ad->update(['category_id' => $furniture->id]);

    expect(categoryAdsCount('cars'))->toBe(0)
        ->and(categoryAdsCount('furniture'))->toBe(1);
});

it('drops and restores the counts when the seller is suspended and reinstated', function (): void {
    Ad::factory()->active()->create(['user_id' => $this->seller->id, 'category_id' => $this->cars->id]);
    expect(categoryAdsCount('cars'))->toBe(1);

    $this->seller->update(['status' => UserStatus::SUSPENDED->value]);
    expect(categoryAdsCount('cars'))->toBe(0);

    $this->seller->update(['status' => UserStatus::ACTIVE->value]);
    expect(categoryAdsCount('cars'))->toBe(1);
});

it('lets ads be filed under a category once the admin bulk-deletes its last sub-category', function (): void {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);

    $tools = Category::query()->create([
        'parent_id' => Category::query()->where('slug', 'home-and-garden')->value('id'),
        'slug' => 'garden-tools',
        'name' => ['ar' => 'أدوات الحديقة', 'en' => 'Garden tools'],
        'order' => 50,
    ]);
    $mowers = Category::query()->create([
        'parent_id' => $tools->id,
        'slug' => 'mowers',
        'name' => ['ar' => 'جزازات', 'en' => 'Mowers'],
        'order' => 0,
    ]);

    $payload = [
        'category_id' => $tools->id,
        'location_id' => Location::query()->value('id'),
        'title' => 'Garden shears for sale',
        'description' => 'Sharp garden shears with comfortable grips, barely used.',
        'price' => 40,
        'price_type' => 'fixed',
    ];

    Sanctum::actingAs($this->seller, ['*']);
    postJson('/api/v1/ads', $payload)->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['category_id']]]);

    actingAs(User::factory()->create()->assignRole('super_admin'), 'web')
        ->post('/admin/categories/bulk-destroy', ['ids' => [$mowers->id]])
        ->assertRedirect();

    Sanctum::actingAs($this->seller, ['*']);
    postJson('/api/v1/ads', $payload)->assertCreated();
});
