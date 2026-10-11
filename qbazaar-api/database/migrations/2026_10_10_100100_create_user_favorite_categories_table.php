<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories a user pinned with "Add to Favorite" on a category page.
 *
 *  - The unique (user_id, category_id) pair makes PUT idempotent at the
 *    schema layer, even under a double tap.
 *  - (user_id, created_at) serves the newest-first list; the category_id
 *    foreign key carries its own index for the cascade on category delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_favorite_categories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'category_id'], 'user_favorite_categories_pair_unique');
            $table->index(['user_id', 'created_at'], 'user_favorite_categories_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_favorite_categories');
    }
};
