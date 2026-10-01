<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The home feed's "best selling" section orders the live ads by
 * favorites_count, then published_at. With status leading, the warmer reads
 * the top rows straight off the index instead of sorting every live ad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->index(['status', 'favorites_count', 'published_at'], 'ads_status_favorites_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->dropIndex('ads_status_favorites_idx');
        });
    }
};
