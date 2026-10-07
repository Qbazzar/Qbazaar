<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User::latestListedAd (where a seller sells from) takes the newest publicly
 * listed ad per user. Its MAX(published_at) per user can only be read from
 * the index when every listed-ad condition is an index column ahead of
 * published_at; ads_user_status_published_idx lacks seller_active and
 * deleted_at, so MySQL scanned every live ad of the sellers instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->index(['user_id', 'status', 'seller_active', 'deleted_at', 'published_at'], 'ads_user_listed_published_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->dropIndex('ads_user_listed_published_idx');
        });
    }
};
