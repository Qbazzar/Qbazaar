<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Featured ads — admin-curated placement on the homepage.
     *
     * Stored as a boolean alongside an index on `(featured, published_at)` so
     * the featured-feed query can pull the latest dozen without a sort + extra
     * filter step. The column defaults to false so existing rows stay opt-out.
     *
     * The admin panel exposes the toggle on the ad page.
     */
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->boolean('featured')->default(false)->after('favorites_count');

            $table->index(['featured', 'published_at'], 'ads_featured_published_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->dropIndex('ads_featured_published_idx');
            $table->dropColumn('featured');
        });
    }
};
