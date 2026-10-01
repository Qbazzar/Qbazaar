<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Public listings used to filter on the seller's status with an EXISTS on
 * users for every candidate row. The status is copied onto the ad instead
 * (kept in step by SellerListingsVisibilityService), so the feed filters and
 * sorts on one ads index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->boolean('seller_active')->default(true)->after('status');
            $table->index(['status', 'seller_active', 'published_at'], 'ads_status_seller_published_idx');
        });

        DB::table('ads')
            ->whereIn('user_id', DB::table('users')->select('id')->where('status', '!=', 'active'))
            ->update(['seller_active' => false]);
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->dropIndex('ads_status_seller_published_idx');
            $table->dropColumn('seller_active');
        });
    }
};
