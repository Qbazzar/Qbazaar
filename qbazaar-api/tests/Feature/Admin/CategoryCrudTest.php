<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Admin\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Admin\Resources\CategoryResource\Pages\ListCategories;
use App\Models\Category;
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

it('creates a category', function (): void {
    Livewire::test(CreateCategory::class)
        ->fillForm([
            'slug' => 'electronics',
            'name' => ['en' => 'Electronics', 'ar' => 'إلكترونيات'],
            'order' => 1,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $category = Category::query()->where('slug', 'electronics')->first();
    expect($category)->not->toBeNull();
    expect($category->name['en'])->toBe('Electronics');
});

it('edits a category', function (): void {
    $category = Category::create([
        'slug' => 'cars',
        'name' => ['en' => 'Cars', 'ar' => 'سيارات'],
        'order' => 0,
        'is_active' => true,
    ]);

    Livewire::test(EditCategory::class, ['record' => $category->getKey()])
        ->fillForm([
            'slug' => 'cars',
            'name' => ['en' => 'Vehicles', 'ar' => 'مركبات'],
            'order' => 5,
            'is_active' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $category->refresh();
    expect($category->name['en'])->toBe('Vehicles');
    expect($category->is_active)->toBeFalse();
    expect($category->order)->toBe(5);
});

it('deletes a category', function (): void {
    $category = Category::create([
        'slug' => 'temp',
        'name' => ['en' => 'Temp', 'ar' => 'مؤقت'],
        'order' => 0,
        'is_active' => true,
    ]);

    Livewire::test(ListCategories::class)
        ->callTableAction('delete', $category);

    expect(Category::query()->whereKey($category->getKey())->exists())->toBeFalse();
});
