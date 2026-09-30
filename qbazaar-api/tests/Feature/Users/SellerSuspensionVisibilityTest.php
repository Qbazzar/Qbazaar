<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\UserStatus;
use App\Models\Ad;
use App\Models\User;
use App\Services\Users\SellerListingsVisibilityService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->seller = User::factory()->create();
    $this->ad = $this->makeAd($this->seller, [
        'status' => AdStatus::ACTIVE->value,
        'published_at' => now()->subDay(),
        'featured' => true,
    ]);
});

function suspendSeller(User $admin, User $seller): void
{
    actingAs($admin)->post("/admin/users/{$seller->id}/suspend")->assertRedirect();
    app('auth')->forgetGuards();
}

function activateSeller(User $admin, User $seller): void
{
    actingAs($admin)->post("/admin/users/{$seller->id}/activate")->assertRedirect();
    app('auth')->forgetGuards();
}

it('hides a suspended seller\'s ads from the public feed, featured list and ad page', function (): void {
    getJson('/api/v1/ads')->assertOk()->assertJsonCount(1, 'data');

    suspendSeller($this->admin, $this->seller);

    getJson('/api/v1/ads')->assertOk()->assertJsonCount(0, 'data');
    getJson('/api/v1/ads/featured')->assertOk()->assertJsonCount(0, 'data');
    getJson("/api/v1/ads/{$this->ad->id}")->assertNotFound();
});

it('hides a suspended seller\'s ads from similar-ad lists', function (): void {
    $other = $this->makeAd(User::factory()->create(), [
        'status' => AdStatus::ACTIVE->value,
        'category_id' => $this->ad->category_id,
    ]);

    getJson("/api/v1/ads/{$other->id}/similar")->assertOk()->assertJsonCount(1, 'data');

    suspendSeller($this->admin, $this->seller);

    getJson("/api/v1/ads/{$other->id}/similar")->assertOk()->assertJsonCount(0, 'data');
});

it('keeps the ads\' own status so reactivation restores them', function (): void {
    suspendSeller($this->admin, $this->seller);

    expect($this->ad->fresh()->status)->toBe(AdStatus::ACTIVE);

    activateSeller($this->admin, $this->seller);

    getJson('/api/v1/ads')->assertOk()->assertJsonCount(1, 'data');
    getJson("/api/v1/ads/{$this->ad->id}")->assertOk();
});

it('does not revive an ad an admin blocked while the seller was suspended', function (): void {
    suspendSeller($this->admin, $this->seller);
    $this->ad->forceFill(['status' => AdStatus::BLOCKED])->save();

    activateSeller($this->admin, $this->seller);

    expect($this->ad->fresh()->status)->toBe(AdStatus::BLOCKED);
    getJson('/api/v1/ads')->assertOk()->assertJsonCount(0, 'data');
});

it('excludes a suspended seller\'s ads from the search index', function (): void {
    expect($this->ad->fresh()->shouldBeSearchable())->toBeTrue();

    suspendSeller($this->admin, $this->seller);

    expect($this->ad->fresh()->shouldBeSearchable())->toBeFalse();
});

it('pulls the seller\'s ads from search on suspension and re-indexes them on activation', function (): void {
    $this->mock(SellerListingsVisibilityService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('hide')->once()->withArgs(fn (User $user): bool => $user->is($this->seller));
        $mock->shouldReceive('restore')->once()->withArgs(fn (User $user): bool => $user->is($this->seller));
    });

    suspendSeller($this->admin, $this->seller);
    activateSeller($this->admin, $this->seller);
});

it('hides listings whenever a seller leaves the active status, not only on admin suspension', function (): void {
    $this->mock(SellerListingsVisibilityService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('hide')->once();
        $mock->shouldNotReceive('restore');
    });

    $this->seller->forceFill(['status' => UserStatus::DEACTIVATED])->save();
    $this->seller->forceFill(['status' => UserStatus::PENDING_DELETION])->save();
});

it('leaves search untouched when the status does not cross the active boundary', function (): void {
    $this->mock(SellerListingsVisibilityService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('hide');
        $mock->shouldNotReceive('restore');
    });

    $this->seller->forceFill(['full_name' => 'Renamed Seller'])->save();
});

it('drops a suspended seller\'s ads from search results', function (): void {
    config(['scout.driver' => 'collection']);
    $searchIds = fn (): array => Ad::search('')->get()->pluck('id')->all();

    expect($searchIds())->toContain($this->ad->id);

    suspendSeller($this->admin, $this->seller);

    expect($searchIds())->not->toContain($this->ad->id);
});
