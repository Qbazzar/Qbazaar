<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            // GET /ads?sort=most_viewed orders by views_count, published_at, id.
            $table->index(['status', 'views_count', 'published_at'], 'ads_status_views_idx');
            // SyncAdViewCountsJob: public ads touched since the last sync.
            $table->index(['status', 'updated_at'], 'ads_status_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->dropIndex('ads_status_views_idx');
            $table->dropIndex('ads_status_updated_idx');
        });
    }
};
