<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\FavoriteCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FavoriteCategory>
 */
class FavoriteCategoryFactory extends Factory
{
    protected $model = FavoriteCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::query()->inRandomOrder()->value('id'),
        ];
    }
}
