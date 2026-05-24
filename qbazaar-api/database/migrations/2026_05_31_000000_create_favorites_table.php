<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Favorites — per-user "saved ad" set.
     *
     *  - ULID primary so external references never expose enumerable ids.
     *  - The unique composite `(user_id, ad_id)` guarantees idempotent
     *    favouriting at the schema layer; the ToggleFavoriteAction relies
     *    on this constraint to keep the denormalised `ads.favorites_count`
     *    in sync without a race-prone "select then insert" probe.
     *  - `(user_id, created_at desc)` index supports the "list my favourites,
     *    newest first" feed used by the account screen.
     *  - No `updated_at` — favourites are immutable: toggling off deletes
     *    the row rather than mutating a flag, so a second timestamp would
     *    only ever equal `created_at`.
     */
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignUlid('ad_id')
                ->constrained('ads')
                ->cascadeOnDelete();

            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'ad_id'], 'favorites_user_ad_unique');
            $table->index(['user_id', 'created_at'], 'favorites_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
