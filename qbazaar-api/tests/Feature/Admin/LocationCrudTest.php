<?php

declare(strict_types=1);

use App\Enums\LocationType;
use App\Filament\Admin\Resources\LocationResource\Pages\CreateLocation;
use App\Filament\Admin\Resources\LocationResource\Pages\EditLocation;
use App\Filament\Admin\Resources\LocationResource\Pages\ListLocations;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    actingAs($admin);
});

it('creates a location', function (): void {
    Livewire::test(CreateLocation::class)
        ->fillForm([
            'slug' => 'doha',
            'name' => ['en' => 'Doha', 'ar' => 'الدوحة'],
            'type' => LocationType::CITY->value,
            'order' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $location = Location::query()->where('slug', 'doha')->first();
    expect($location)->not->toBeNull();
    expect($location->type)->toBe(LocationType::CITY);
});

it('edits a location', function (): void {
    $location = Location::create([
        'slug' => 'wakra',
        'name' => ['en' => 'Wakra', 'ar' => 'الوكرة'],
        'type' => LocationType::CITY->value,
        'order' => 0,
    ]);

    Livewire::test(EditLocation::class, ['record' => $location->getKey()])
        ->fillForm([
            'slug' => 'wakra',
            'name' => ['en' => 'Al Wakrah', 'ar' => 'الوكرة'],
            'type' => LocationType::DISTRICT->value,
            'order' => 3,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $location->refresh();
    expect($location->name['en'])->toBe('Al Wakrah');
    expect($location->type)->toBe(LocationType::DISTRICT);
});

it('deletes a location', function (): void {
    $location = Location::create([
        'slug' => 'temp-loc',
        'name' => ['en' => 'Temp', 'ar' => 'مؤقت'],
        'type' => LocationType::AREA->value,
        'order' => 0,
    ]);

    Livewire::test(ListLocations::class)
        ->callTableAction('delete', $location);

    expect(Location::query()->whereKey($location->getKey())->exists())->toBeFalse();
});
