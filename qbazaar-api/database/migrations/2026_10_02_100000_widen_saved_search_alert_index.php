<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The alert pre-filter narrows on category, then location. With both in
     * the index, the location test runs on index entries (index condition
     * pushdown) instead of on every row of the category.
     */
    public function up(): void
    {
        Schema::table('saved_searches', function (Blueprint $table): void {
            $table->index(['alerts_enabled', 'category_id', 'location_id'], 'saved_searches_alerts_category_location_idx');
            $table->dropIndex('saved_searches_alerts_category_idx');
        });
    }

    public function down(): void
    {
        Schema::table('saved_searches', function (Blueprint $table): void {
            $table->index(['alerts_enabled', 'category_id'], 'saved_searches_alerts_category_idx');
            $table->dropIndex('saved_searches_alerts_category_location_idx');
        });
    }
};
