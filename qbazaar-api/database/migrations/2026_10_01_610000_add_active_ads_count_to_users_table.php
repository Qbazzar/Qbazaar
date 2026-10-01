<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A seller's live-ad count is shown on every ad detail, chat header, seller
 * card and company row, so it is stored on users and kept in step by
 * SellerAdsCountObserver instead of being counted per view.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // No index of its own: the counter moves with every listing change, and the
            // one query that sorts by it (home featured sellers, run by the catalog
            // warmer) is narrowed by users_directory_idx first.
            $table->unsignedInteger('active_ads_count')->default(0)->after('following_count');
        });

        DB::table('users')->update([
            'active_ads_count' => DB::raw(
                "(SELECT COUNT(*) FROM ads WHERE ads.user_id = users.id AND ads.status = 'active' AND ads.deleted_at IS NULL)",
            ),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('active_ads_count');
        });
    }
};
