<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Filament\Admin\Resources\AdResource\Pages\ListAds;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seedReferenceData();

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    actingAs($admin);

    $this->seller = User::factory()->create();
});

it('toggles the featured flag on an ad', function (): void {
    $ad = $this->makeAd($this->seller, ['featured' => false]);

    Livewire::test(ListAds::class)
        ->callTableAction('feature', $ad);

    expect($ad->refresh()->featured)->toBeTrue();

    Livewire::test(ListAds::class)
        ->callTableAction('feature', $ad);

    expect($ad->refresh()->featured)->toBeFalse();
});

it('force-expires an active ad', function (): void {
    $ad = $this->makeAd($this->seller, [
        'status' => AdStatus::ACTIVE->value,
        'published_at' => now(),
        'expires_at' => now()->addDays(30),
    ]);

    Livewire::test(ListAds::class)
        ->callTableAction('force_expire', $ad);

    expect($ad->refresh()->status)->toBe(AdStatus::EXPIRED);
});

it('hides the force_expire action on a non-active ad', function (): void {
    $ad = $this->makeAd($this->seller, ['status' => AdStatus::PENDING->value]);

    Livewire::test(ListAds::class)
        ->assertTableActionHidden('force_expire', $ad);
});
